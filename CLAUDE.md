# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start dev server
symfony serve
# or
php -S localhost:8000 -t public

# Run all tests (requires a running database matching DATABASE_URL in .env.test)
php bin/phpunit

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

### Domain Model

- **TimeEntry**: Open interval (`stoppedAt = null`) means a timer is currently running. One user can only have one running entry at a time.
- **AbsenceRequest**: Workflow entity with statuses `pending → approved/rejected` or `cancelled`. The `approve()`/`reject()` methods on the entity handle state transitions and record which admin acted.
- **AbsenceType**: Admin-configured absence categories (e.g. vacation, sick leave).
- **AbsenceQuota**: Per-user, per-type, per-year day allowance. `quotaDays = null` means unlimited.
- **Holiday**: Named dates assigned to specific users via a many-to-many join (`holiday_user`). These are personal non-working days (e.g. regional public holidays) that reduce Soll-time. `halfDay = true` marks a half-day holiday (only half the Soll-time is waived).
- **AbsenceRequest.halfDay**: Half-day absence; only valid for single-day requests (`startDate == endDate`). Two half-day requests may share one day unless it is a half-day holiday (holiday + absences must not exceed one day; `DayPortion::fitsAlongside()`); every other overlap is rejected. Date/overlap validation for new requests lives in `AbsenceRequestValidator`.
- **AbsenceQuota.quotaDays / AbsenceType.defaultYearlyQuotaDays**: Stored as `NUMERIC(5,1)` (string property, float getters) so quotas can be half days.
- **Settings**: Single-row global config (auto-pause thresholds, branding, `halfDaysEnabled`). Use `SettingsRepository::getOrCreate()` to retrieve it. `halfDaysEnabled` only controls whether half-day inputs are offered (holiday/absence forms, 0.5 quota steps); existing half-day records are always calculated as half days, regardless of the toggle.
- **WorkLocationType**: Admin-configured work location categories (e.g. Büro, Home Office, Geschäftsreise). Has `name`, `keyName`, `isActive`, `isDefault`, and optional `icon` (FontAwesome class string). Exactly one type should carry `isDefault = true`; the admin controller enforces this on save.
- **WorkLocation**: Per-user, per-day location record. Unique constraint on `(user_id, date)`. Absence of a record means the default type is used as a display fallback (only on days with actual time entries). Users can export a yearly location summary as CSV via `GET /time-tracking/{year}/work-locations.csv` (`_time_locations_csv`). Today's location can also be set directly from the dashboard via `POST /time/work-location` (`_time_location_dashboard`).

### Core Calculation Services

All day-based calculations work with **day fractions** (1.0 = full day, 0.5 = half day) via the static helper **`DayPortion`**: remaining Soll fraction of a workday = `max(0, 1 − holiday − absence)`, chargeable absence = `min(absence, 1 − holiday)` (so a full-day absence on a half-day holiday counts 0.5 days). Day counts are therefore `float`; render them in Twig with the `|days` filter (`DayFormatter`: `2,5` in German, `2.5` in English, whole numbers without decimals).

- **`TimeTrackingCalculator`**: Computes net worked seconds from `TimeEntry[]`. Clips entries to date ranges, then applies daily break deductions per `Settings` (>6 h worked → subtract configured pause minutes, >9 h worked → subtract a larger pause).
- **`WorktimeSollCalculator`**: Computes target (Soll) seconds for a date range for a given user. Skips weekends, user-assigned holidays, and approved absences.
- **`AbsenceDayCalculator`**: Counts chargeable absence days (float), excluding weekends, user-assigned holidays (half-day holidays count half), and (optionally) global public holidays via `HolidayProviderInterface`. Prefer `countForRequest()`, which uses the requester's holidays and the request's half-day flag.
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

Tests use `.env.test` and require a real database (not mocked). The CI workflow spins up a MariaDB 11.8 service container.
