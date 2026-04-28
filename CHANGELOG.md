# Changelog

## [Unreleased]

## [1.1.0] - 2026-04-28

### Added
- **Arbeitsort-Tracking**: Pro Arbeitstag kann der Arbeitsort (Büro, Home Office, Geschäftsreise) erfasst werden
  - Ortstypen sind admin-konfigurierbar (`/admin/work-location-types`) mit Name, Schlüssel, optionalem FontAwesome-Icon und Standard-Flag
  - Standardmäßig werden drei Typen angelegt: Büro (Standard), Home Office, Geschäftsreise
  - Der Arbeitsort wird auf der Tagesansicht (`/time-tracking/day/{date}`) als Dropdown angezeigt und ist dort setz- und nachträglich änderbar
  - In der Jahresübersicht (`/time-tracking/{year}`) und der Admin-Jahresansicht erscheint der Ort als Badge im Tag-Feld — nur für Tage mit gebuchter Arbeitszeit; der Standard-Typ wird als Fallback angezeigt wenn kein expliziter Eintrag existiert
- GitHub Actions CI workflow: runs PHPUnit tests against a MariaDB 11.8 service container on push/PR to `main`

### Changed
- `.env.dev` removed from repository and added to `.gitignore`

## [1.0.0] - 2026-03-18

### Added
- Initial release
- Time tracking
- Absence management
- Approval workflows
