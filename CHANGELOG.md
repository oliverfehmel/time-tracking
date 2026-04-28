# Changelog

## [Unreleased]

## [1.1.2] - 2026-04-28

### Fixed
- **Delta hours capped to today**: Soll hours in the yearly overview (monthly totals, individual day rows) and on the dashboard (week/month) are now only counted up to and including today. Previously, future workdays were included in the Soll, making the delta appear as a deficit even though those days haven't happened yet.

## [1.1.1] - 2026-04-28

### Added
- **Work location CSV export**: Users can download a CSV file at `/time-tracking/{year}/work-locations.csv` listing the number of booked working days per location for the year (useful for tax returns). A download button appears in the yearly overview next to the year selector.

## [1.1.0] - 2026-04-28

### Added
- **Work location tracking**: The work location (e.g. office, home office, business trip) can be recorded per working day
  - Location types are admin-configurable (`/admin/work-location-types`) with name, key, optional FontAwesome icon, and a default flag
  - Three types are created by default: Office (default), Home Office, Business Trip
  - The work location is shown as a dropdown on the day view (`/time-tracking/day/{date}`) and can be set or changed there at any time
  - In the yearly overview (`/time-tracking/{year}`) and the admin year view, the location appears as a badge in the day row — only for days with booked working time; the default type is shown as a fallback when no explicit record exists
- GitHub Actions CI workflow: runs PHPUnit tests against a MariaDB 11.8 service container on push/PR to `main`

### Changed
- `.env.dev` removed from repository and added to `.gitignore`

## [1.0.0] - 2026-03-18

### Added
- Initial release
- Time tracking
- Absence management
- Approval workflows
