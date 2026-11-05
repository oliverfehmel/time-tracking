---
name: feature
description: End-to-end workflow for implementing a feature or non-trivial fix in this Symfony app — branch, data model and migration, logic with tests, UI with translations, docs and changelog, PR. Use when the user asks to build, add or change functionality (not for one-line fixes).
argument-hint: "<short description of the feature>"
---

# Feature workflow

Goal: a change that is complete the way the half-days feature (PR #4) was — model, logic, UI, translations, tests, docs — and reviewable commit by commit.

## 0. Understand and plan

- Read `CLAUDE.md` and the code the feature touches (entities, services, controllers, templates, existing tests).
- If requirements are ambiguous (e.g. how it interacts with half days, holidays, absences, roles, the settings toggles), ask before building.
- For larger features, outline the steps first and list the edge cases you will test.

## 1. Branch

Follow the `github` skill: `feature/<kebab-name>` (or `fix/…`) from up-to-date `main`.

## 2. Data model (if needed)

- Entity changes in `src/Entity` with Doctrine attributes; repository queries in `src/Repository`.
- Generate and review a migration — see the `migration` skill.
- New global configuration goes into `Settings` (single row, `SettingsRepository::getOrCreate()`), with an admin form field.

## 3. Logic

- Business logic belongs in `src/Service`, not in controllers or templates. Controllers stay thin.
- Reuse existing building blocks: `DayPortion` for any day fraction, `TimeTrackingCalculator`, `WorktimeSollCalculator`, `AbsenceDayCalculator`, `AbsenceRequestValidator`, `DayFormatter` / `|days`.
- Day counts are `float`. Soll is capped to today.
- Respect roles: user routes only see the current user's data; admin routes carry `#[IsGranted('ROLE_ADMIN')]`.

## 4. Tests

- PHPUnit tests in `tests/` mirroring `src/` (`tests/Service/…Test.php`, `tests/Entity/…`, `tests/Form/…`, `tests/Twig/…`).
- Cover the edge cases: weekends, user holidays (full and half), approved/pending/cancelled absences, half-day requests, year boundaries, running timers, empty data, the feature toggle on and off.
- Run `php bin/phpunit` (needs the test DB from `.env.test`); run single files while iterating.

## 5. UI

- Twig templates in `templates/`, Bootstrap 5 classes, FontAwesome icons.
- JavaScript only as Stimulus controllers in `assets/controllers/`; new JS packages via `php bin/console importmap:require`.
- Forms in `src/Form`. State-changing actions are POST with a CSRF token (`csrf_token('<action>_' ~ id)` + `isCsrfTokenValid`).
- Links that log out, switch user or download get `data-turbo-prefetch="false"`.

## 6. Translations

- Every user-facing string is a key in **both** `translations/messages.de.yaml` and `translations/messages.en.yaml` (validator messages in `validators.*.yaml`). This includes flash messages and notification mails.
- Check: `php bin/console lint:yaml translations` and `php bin/console debug:translation de --only-missing` / `en --only-missing`.

## 7. Verify

- `php bin/phpunit`
- `php bin/console lint:twig templates`, `php bin/console lint:container`
- If the UI changed: start the app (`symfony serve`, see `.claude/launch.json`) and look at the affected pages, with the relevant setting on and off. If you cannot, say so in the PR.

## 8. Docs

- `CHANGELOG.md`: add the entry under the unreleased section at the top (create `## [Unreleased]` if missing), grouped as **Added / Changed / Removed / Fixed**, written for admins/users of the app — bold lead-in, then what changes for them.
- `CLAUDE.md`: update the domain model / services sections if they changed.
- `README.md`: update if setup, configuration or user-facing features listed there changed.

## 9. Review and PR

- Run the `pr-review` skill on the branch before opening the PR (fresh-context review), fix confirmed findings.
- Open the PR as described in the `github` skill — only when the user asks.
