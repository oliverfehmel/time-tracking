---
name: dependency-update
description: Update Composer packages, the Symfony minor/major version and importmap JavaScript packages, fix resulting deprecations and document the update. Use when the user asks for composer/Symfony/dependency/security updates.
argument-hint: "[symfony target version, e.g. 8.2 | 'patch' | 'security']"
disable-model-invocation: true
---

# Dependency update (Composer, Symfony, importmap)

Precedent: PRs #2 and #3 (`chore/symfony-8.1-dependency-update`) — read their commits and the 1.2.0 CHANGELOG entries for the expected result.

## Ground rules of this repo

- Symfony packages use `*` as their own constraint and are pinned centrally via `extra.symfony.require` in `composer.json` (currently `8.1.*`). Never put version constraints on individual `symfony/*` packages.
- `config.bump-after-update` is `false` on purpose — Composer must not rewrite constraints to `>=` ranges. Non-Symfony constraints are raised by hand to the new minimum (`^x.y.z`).
- **PHP versions**: CI runs PHP **8.4**, local and production run 8.5. The lock file must be installable on 8.4: after updating, run `composer why-not php 8.4.99` (any 8.4 patch release; CI installs the latest one) — it must report "no installed package depending on php in versions not matching". If a package needs 8.5, keep the older version or discuss raising the requirement with the user.
- JavaScript packages come from `importmap.php`, not npm.

## 1. Prepare

- Clean working tree, new branch `chore/<description>` from up-to-date `main` (see `github` skill).
- Baseline: `php bin/phpunit` green before touching anything; note the number of tests.
- Inventory:
  - `composer outdated --direct` (and `composer outdated` for transitive)
  - `composer audit`
  - `php bin/console importmap:outdated` and `php bin/console importmap:audit`
- Present the list to the user, grouped as **security fixes**, **patch/minor**, **major**, with links to upgrade notes for majors (`UPGRADE-x.y.md` of Symfony, package changelogs). Confirm the scope before continuing when there are majors or a Symfony version jump.

## 2. Symfony version jump (only if requested)

- Set `extra.symfony.require` to the new `x.y.*`.
- Read `UPGRADE-x.y.md` for the new version and check the app's usages of changed APIs.
- `composer update "symfony/*" --with-all-dependencies`
- Run recipe updates: `composer recipes:update` — apply them one by one, review each diff, keep local customisations.

## 3. Other packages

- `composer update --with-all-dependencies` (or targeted: `composer update vendor/pkg --with-dependencies`).
- Raise the constraints in `composer.json` for directly required non-Symfony packages to the installed versions (`^x.y.z`), then `composer update --lock` and `composer validate`.
- Majors (e.g. PHPUnit, Doctrine ORM, UX bundles): one commit per major, with the code changes it needs.

## 4. JavaScript (importmap)

- `php bin/console importmap:update` (or `importmap:update <pkg>` for single packages).
- Read release notes for majors. Precedent: Turbo 8 hover prefetch — links that log out, switch users or download need `data-turbo-prefetch="false"` / download handling.
- `php bin/console importmap:audit` must be clean afterwards.

## 5. Fix deprecations

- `php bin/phpunit` — PHPUnit reports deprecations; also check `var/log/dev.log` and the profiler after clicking through the app.
- `php bin/console debug:container --deprecations`
- `php bin/console lint:container`, `lint:twig templates`, `lint:yaml config translations`
- Typical precedents: `SortDirection` enum instead of `'ASC'`/`'DESC'` in `orderBy()`, removed config options in `config/packages/*.yaml`, migrations with `isTransactional(): false`.
- Commit deprecation fixes separately from the version bump.

## 6. Verify

- `php bin/phpunit` green, same or higher test count as the baseline.
- `composer why-not php 8.4.99` → no conflicting packages.
- `composer audit` and `importmap:audit` → no advisories.
- Start the app (`symfony serve`) and click through login, dashboard (start/stop timer), year view, absences, admin pages. Say explicitly if this was not done.

## 7. Document

`CHANGELOG.md`, under `## [Unreleased]`:

- **Changed**: "Dependencies updated to Symfony x.y" — mention notable majors and anything that changes for people hosting the app (PHP requirement, removed packages, web server config).
- **Fixed**: security advisories (with GHSA id) and deprecation fixes.
- **Removed**: dropped packages.

Update `README.md` / `CLAUDE.md` if requirements (PHP, Symfony version) changed. Then follow the `github` skill to open the PR — reviewer notes list majors, recipe changes and anything not verified.
