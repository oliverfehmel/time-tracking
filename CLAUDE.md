# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start dev server
symfony serve
# or
php -S localhost:8000 -t public

# Run all tests (requires a database; DATABASE_URL in .env.test.local, Doctrine appends "_test")
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/phpunit

# Dump the generated OpenAPI document
php bin/console nelmio:apidoc:dump

# Run a single test file
php bin/phpunit tests/Service/TimeTrackingCalculatorTest.php

# Database migrations
php bin/console doctrine:migrations:migrate

# Create a user
php bin/console app:create-user <email> <password> [ROLE_USER|ROLE_ADMIN]

# Clear cache
php bin/console cache:clear
```

## Architecture

Standard Symfony 8 MVC: `src/Controller`, `src/Entity`, `src/Repository`, `src/Service`, `src/Form`, templates in `templates/`.

### Roles & Access Control

Two roles: `ROLE_USER` (all authenticated users) and `ROLE_ADMIN` (which implicitly includes `ROLE_USER` and `ROLE_ALLOWED_TO_SWITCH`). All `/admin/*` routes require `ROLE_ADMIN`. Admins can impersonate users via the `?_switch_user=email` query parameter.

Two firewalls: `api` (`^/api/v1`, stateless, bearer API tokens via `ApiTokenHandler`) and `main` (form login, session, remember-me, user switching). `/api` (guide) and `/api/docs` (Swagger UI) belong to `main`. Object-level access ("own data, admins everything") is decided by `DataAccessVoter` (`DATA_ACCESS` on User, TimeEntry, AbsenceRequest, Holiday).

### REST API (`/api/v1`, see `docs/adr/0001-rest-api-v1.md`)

- Controllers in `src/Controller/Api/` extend `AbstractApiController` (envelope `{data, meta}`, pagination, `resolveUser()` for `userId`, `ensureAccess()` → 404 for foreign data, `enforceLocks()` = not admin). They stay thin and call the same services as the UI.
- Request DTOs in `src/Api/Dto` (`#[MapRequestPayload]`/`#[MapQueryString]`); PATCH is a merge patch via `ApiPayload::merge()` over `XInput::fromEntity()`; only sent fields (plus `Callback` cross-field rules) are validated, so legacy values created via the UI stay editable. Response classes in `src/Api/View` (`X::from()`), dates via `ApiDates` (RFC 3339 in the server time zone, `Y-m-d`).
- Errors: throw `DomainRuleViolation` (business rules) or `ApiProblem`; `ApiExceptionListener` renders RFC 9457 problems (`code` + translated `detail` from `api.problem.*`, language from `Accept-Language`). `ApiSecurityResponder` handles 401/403 of the firewall, `ApiRequestListener` sets the locale and rejects writes with read-only tokens.
- OpenAPI via NelmioApiDocBundle: document every operation with `OA\Get/Post/…` (summary), `#[DataResponse(View::class)]` and `#[ProblemResponse(status)]`; `ApiDocumentationTest` fails for undocumented routes, and `ApiTestCase` validates every response against the generated schema.

### Domain Model

- **TimeEntry**: Open interval (`stoppedAt = null`) means a timer is currently running. One user can only have one running entry at a time.
- **AbsenceRequest**: Workflow entity with statuses `pending → approved/rejected` or `cancelled`. The `approve()`/`reject()` methods on the entity handle state transitions and record which admin acted.
- **AbsenceType**: Admin-configured absence categories (e.g. vacation, sick leave).
- **AbsenceQuota**: Per-user, per-type, per-year day allowance. `quotaDays = null` means unlimited.
- **Holiday**: Named dates assigned to specific users via a many-to-many join (`holiday_user`). These are personal non-working days (e.g. regional public holidays) that reduce Soll-time. `halfDay = true` marks a half-day holiday (only half the Soll-time is waived).
- **AbsenceRequest.halfDay**: Half-day absence; only valid for single-day requests (`startDate == endDate`). Two half-day requests may share one day unless it is a half-day holiday (holiday + absences must not exceed one day; `DayPortion::fitsAlongside()`); every other overlap is rejected. Date/overlap validation for new requests lives in `AbsenceRequestValidator`.
- **AbsenceQuota.quotaDays / AbsenceType.defaultYearlyQuotaDays**: Stored as `NUMERIC(5,1)` (string property, float getters) so quotas can be half days.
- **Settings**: Single-row global config (auto-pause thresholds, branding, `halfDaysEnabled`). Use `SettingsRepository::getOrCreate()` to retrieve it. `halfDaysEnabled` only controls whether half-day inputs are offered (holiday/absence forms, 0.5 quota steps); existing half-day records are always calculated as half days, regardless of the toggle.
- **ApiToken**: Personal REST API token of a user (`onDelete: CASCADE`). Stores only the SHA-256 hash and a display prefix of `tt_<64 hex>`; `scope` is `read` or `read_write`; optional `expiresAt`; `lastUsedAt` is written at most once per minute. Created/revoked via `ApiTokenManager`; never created while impersonating.
- **WorkLocationType**: Admin-configured work location categories (e.g. Büro, Home Office, Geschäftsreise). Has `name`, `keyName`, `isActive`, `isDefault`, and optional `icon` (FontAwesome class string). Exactly one type should carry `isDefault = true`; the admin controller enforces this on save.
- **WorkLocation**: Per-user, per-day location record. Unique constraint on `(user_id, date)`. Absence of a record means the default type is used as a display fallback (only on days with actual time entries). Users can export a yearly location summary as CSV via `GET /time-tracking/{year}/work-locations.csv` (`_time_locations_csv`). Today's location can also be set directly from the dashboard via `POST /time/work-location` (`_time_location_dashboard`).

### Core Calculation Services

All day-based calculations work with **day fractions** (1.0 = full day, 0.5 = half day) via the static helper **`DayPortion`**: remaining Soll fraction of a workday = `max(0, 1 − holiday − absence)`, chargeable absence = `min(absence, 1 − holiday)` (so a full-day absence on a half-day holiday counts 0.5 days). Day counts are therefore `float`; render them in Twig with the `|days` filter (`DayFormatter`: `2,5` in German, `2.5` in English, whole numbers without decimals).

- **`TimeTrackingCalculator`**: Computes net worked seconds from `TimeEntry[]`. Clips entries to date ranges, then applies daily break deductions per `Settings` (>6 h worked → subtract configured pause minutes, >9 h worked → subtract a larger pause).
- **`WorktimeSollCalculator`**: Computes target (Soll) seconds for a date range for a given user. Skips weekends, user-assigned holidays, and approved absences.
- **`AbsenceDayCalculator`**: Counts chargeable absence days (float), excluding weekends, user-assigned holidays (half-day holidays count half), and (optionally) global public holidays via `HolidayProviderInterface`. Prefer `countForRequest()`, which uses the requester's holidays and the request's half-day flag.
- **`AbsenceRequestService`**: The absence workflow shared by UI and API — `create()` (active type, half-day toggle, `AbsenceRequestValidator`, quota via `AbsenceQuotaService::checkRequestWithinQuota()`, auto-approval, admin mail), `cancel()`, `assertDecidable()`, `approve()`, `reject()`.
- **`TimeEntryService`** / **`WorkLocationService`** / **`TimeEditPolicy`**: Timer start/stop, time-entry create/update/delete with overlap resolution, setting/clearing a day's work location. `TimeEditPolicy` holds the edit locks for regular users (setting `usersAreAllowedToChangeTimeEntries`, current + previous month); services take `$enforceLocks` (UI: always, API: not for admins). Services use `ClockInterface` (tests: `mockTime()`).
- **`DomainRuleViolation`**: Exception for violated business rules (`rule` code, translation key + parameters, HTTP status, `propertyPath`). UI: flash/form error via `trans()`; API: problem response.
- **`AbsenceNotifier`**: Sends Twig-templated emails — to the requester on approval/rejection, and to all admin email addresses when a new request is created.
- **`UserYearReportBuilder`**: Assembles the full year report for a user. Each day row includes `workLocationName` and `workLocationIcon` — resolved from an explicit `WorkLocation` record or (for real workdays with entries) from the default `WorkLocationType`. All work locations for the year are loaded in a single query via `WorkLocationRepository::buildTypeMapForUser()`. Soll seconds are capped to today (exclusive: tomorrow 00:00) so that future workdays do not create a phantom deficit in the delta.
- **`DashboardDataBuilder`**: Assembles all dashboard template variables. Includes `locationTypes` (active types), `todayWorkLocation` (today's explicit record or `null`), and `defaultLocationType` — the latter two are only queried when active location types exist.

### Frontend

Assets are managed with **Symfony AssetMapper** (no Webpack/Encore). JavaScript lives in `assets/` and uses **Stimulus.js** controllers (`assets/controllers/`). Bootstrap 5 and FontAwesome are bundled as static files. Turbo (Hotwire) is enabled for page transitions.

### Environment & Configuration

Copy `.env.example` to `.env.local` for local development. Required variables:
- `DATABASE_URL` — MariaDB/MySQL/PostgreSQL connection string
- `MAILER_DSN` — SMTP transport
- `DEFAULT_URI` — absolute base URL used in outgoing emails
- `MAIL_FROM` — sender address for notification emails
- `DEV_MAIL_RECIPIENT` — overrides all recipients in `dev` environment

Tests use `.env.test` (+ git-ignored `.env.test.local` for `DATABASE_URL`) and require a real database (not mocked). The CI workflow spins up a MariaDB 11.8 service container, creates the test database and runs the migrations. `dama/doctrine-test-bundle` rolls every test back. Base classes in `tests/Support`: `FunctionalTestCase` (browser + `TestDataFactory`, same-origin header for stateless CSRF), `DatabaseTestCase` (services against the DB), `ApiTestCase` (`actAs()`, `api()`, `assertProblem()`, OpenAPI contract check). Notes: the kernel reboots between requests, so create fixtures before the first request of a test; `Request::create()` sends `Accept-Language: en` by default; queued mails are asserted with `assertQueuedEmailCount()`.

Docker: `Dockerfile` (Apache + PHP 8.4, `docker/app/` holds `php.ini`, the vhost and the entrypoint) and `compose.yaml` (services `app`, `worker` for Messenger mails, `db` MariaDB 11.8). The entrypoint runs every console command as `www-data`, generates `APP_SECRET` into the `app_data` volume if it is unset, waits for the database and runs the migrations only in `app` (`RUN_MIGRATIONS=1`). Compose settings use the prefix `TT_` because Compose also reads the app's `.env`. `variables_order = "EGPCS"` is required so Symfony sees the container environment under mod_php. The `docker` CI job runs `docker compose up --wait` and requests `/login`. `docker/public` and `docker/local` are only database containers for local development.

PHP versions: CI runs PHP **8.4**, local development and production (`deploy.sh`) run 8.5. Code and `composer.lock` must stay compatible with 8.4.

## Conventions

- **Language**: code, comments, commits, PRs and `CHANGELOG.md` are in English. All user-facing text is a translation key present in both `translations/messages.de.yaml` and `messages.en.yaml` (validators in `validators.*.yaml`) — no hard-coded strings in templates, controllers, flash messages or mails.
- **Thin controllers**: business logic goes into `src/Service`; controllers carry `#[IsGranted(...)]` at class level (only the public `SecurityController` and `ResetPasswordController` do not). State-changing actions are POST with a CSRF token (`csrf_token('<action>_' ~ id)` / `isCsrfTokenValid`).
- **Day maths** always via `DayPortion`; day counts are `float` and rendered with `|days`.
- **Doctrine**: `orderBy()`/`addOrderBy()` take the `SortDirection` enum; every migration declares `isTransactional(): false` and a working `down()`.
- **Symfony versions** are pinned centrally via `extra.symfony.require`; individual `symfony/*` constraints stay `*`. `bump-after-update` is off on purpose.
- **Frontend**: Stimulus controllers only (no inline scripts); JS packages via `importmap.php`. Links that log out, switch user or download need `data-turbo-prefetch="false"`.
- **Tests** for every new service/entity logic in `tests/`, mirroring `src/`; every API endpoint gets functional tests in `tests/Api` (own data, foreign data → 404, admin, validation, rules).
- **API**: new endpoints follow `docs/adr/0001-rest-api-v1.md`; breaking changes need `/api/v2`.
- **Docs**: user-visible changes get an entry under `## [Unreleased]` in `CHANGELOG.md` (Added / Changed / Removed / Fixed); changes to the domain model or services are reflected in this file.

## Claude Code setup (`.claude/`)

- `skills/feature` — end-to-end workflow for features and non-trivial fixes.
- `skills/github` — branch, commit, PR, CI and merge conventions.
- `skills/pr-review` + `agents/reviewer.md` — review with a fresh-context subagent against the project checklist.
- `skills/migration` — creating and checking Doctrine migrations.
- `skills/dependency-update` (`/dependency-update`, manual only) — Composer, Symfony and importmap updates.
- `skills/release` (`/release`, manual only) — release PR with the finalized changelog, tag after the merge, GitHub release (`main` is protected).
- `settings.json` — shared permissions; personal overrides go into the git-ignored `settings.local.json`.
