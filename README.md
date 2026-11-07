# Time Tracking App

A modern, self-hosted time tracking and absence management application built with Symfony 8.

![Dashboard with running timer, weekly and monthly totals and the absence balance](docs/images/dashboard.png)

## Features

- Time tracking with a start/stop timer, daily and monthly target vs. actual times and automatic break deductions
- Absence management (vacation, sick leave, etc.) with quotas and an approval workflow
- Optional half days: half-day holidays (e.g. Christmas Eve), half-day absence requests and quotas in half days
- Work locations per day (office, home office, business trip, …) with a yearly CSV export
- Role-based access control (admin, user); admins can view every employee's times and impersonate users
- Email notifications for new requests, approvals and rejections
- English and German user interface
- REST API with personal API tokens, Swagger UI and an in-app guide (see below)
- Runs anywhere with Docker Compose (see below)

| Monthly time sheet | Absence approvals |
|---|---|
| ![Monthly time sheet with start, end, actual and target time per day](docs/images/time-tracking-month.png) | ![Approval list with pending and processed absence requests](docs/images/approvals.png) |

## Tech Stack

- PHP >= 8.4
- Symfony 8
- Doctrine ORM
- Twig
- Bootstrap 5
- MySQL / MariaDB / PostgreSQL

## Quick start with Docker

Requirements: Docker with Docker Compose.

```bash
git clone https://github.com/oliverfehmel/time-tracking.git
cd time-tracking
docker compose up -d
```

The first start builds the image and creates the database; then open <http://localhost:8080> and create the first
admin account:

```bash
docker compose exec -u www-data app php bin/console app:create-user admin@example.com 'a-secure-password' ROLE_ADMIN
```

The stack consists of the app (Apache + PHP 8.4, runs the database migrations on every start), a worker that sends the
notification mails and MariaDB 11.8. Data lives in Docker volumes (`database`, `uploads`, `favicon`, `app_data`), so
updating is just:

```bash
git pull
docker compose up -d --build
```

### Settings

Settings are environment variables with the prefix `TT_`. Export them in your shell, put them into a separate file and
start with `docker compose --env-file <file> up -d`, or add a `compose.override.yaml`.

| Variable | Default | Purpose |
|---|---|---|
| `TT_PORT` | `8080` | Port on the host |
| `TT_DEFAULT_URI` | `http://localhost:8080` | Public base URL, used for links in emails |
| `TT_MAILER_DSN` | `null://null` (mails are discarded) | SMTP connection, e.g. `smtp://user:pass@smtp.example.com:587` |
| `TT_MAIL_FROM` | `time-tracking@example.com` | Sender address of notification mails |
| `TT_LOCALE` | `de` | Default language (`de` or `en`) |
| `TT_TIMEZONE` | `Europe/Berlin` | Time zone of the app and the database |
| `TT_DB_PASSWORD` | `time_tracking` | Password of the database user (set it before the first start; the database is not exposed to the host) |
| `TT_APP_SECRET` | generated on first start | Symfony `APP_SECRET`; if empty, a random secret is created once and kept in the `app_data` volume |
| `TT_TRUSTED_PROXIES` | empty | IPs/CIDRs of a reverse proxy that terminates HTTPS, e.g. `172.16.0.0/12` |

For production, put a reverse proxy with HTTPS (e.g. Caddy, Traefik or nginx) in front of the app and set
`TT_DEFAULT_URI` and `TT_TRUSTED_PROXIES`. Back up the database with
`docker compose exec db sh -c 'mariadb-dump -utime_tracking -p"$MARIADB_PASSWORD" time_tracking' > backup.sql`.

## Manual installation

### Requirements

- PHP >= 8.4
- Composer
- Database (MySQL, MariaDB or PostgreSQL)
- Symfony CLI (optional)

### Installation

```bash
git clone https://github.com/oliverfehmel/time-tracking.git
cd time-tracking

composer install
```

#### Configure your database in .env.local:
```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/time_tracking"
```

#### Run migrations:
```bash
php bin/console doctrine:migrations:migrate
```

#### Create the first admin account:
```bash
php bin/console app:create-user admin@example.com 'a-secure-password' ROLE_ADMIN
```

#### Start server:
```bash
symfony serve
```

or

```bash
php -S localhost:8000 -t public
```

## REST API

All data is available as JSON under `/api/v1` (users, time entries, timer, year report, work locations, absence types,
absence requests, quotas, holidays).

1. Create a personal token in the app under **Profile → API tokens** (read-only or read/write, optional expiry).
2. Send it with every request: `curl -H "Authorization: Bearer tt_…" https://your-host/api/v1/me`

A token has the rights of its owner: regular users only reach their own data, admins all data. The in-app page **API**
(`/api`) explains formats, errors and half-day rules; Swagger UI is at `/api/docs`, the OpenAPI document at
`/api/docs.json` (both require a login). Design decisions: [`docs/adr/0001-rest-api-v1.md`](docs/adr/0001-rest-api-v1.md).

## Development

### Run Tests

The test suite needs a database (MariaDB/MySQL). Point `DATABASE_URL` in `.env.test.local` (git-ignored) to a server
where the user may create databases — Doctrine appends `_test` to the database name. Include `serverVersion`, otherwise
`doctrine:database:create` tries to connect to the not yet existing database, e.g.
`DATABASE_URL="mysql://root:secret@127.0.0.1:3306/time_tracking?serverVersion=11.8.2-MariaDB&charset=utf8mb4"`.
Then create the schema once:

```bash
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/phpunit
```

Each test runs in a transaction that is rolled back (`dama/doctrine-test-bundle`).

## Configuration

Adjust .env or create .env.local:

- Database credentials
- Mailer configuration
- App environment
- App Secret

## Contributing

Contributions are welcome! Please read CONTRIBUTING.md

## License

This project is licensed under the MIT License – see the LICENSE file for details.

## Disclaimer

This software is provided "as is" without warranty.
Use at your own risk.
