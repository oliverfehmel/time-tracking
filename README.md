# Time Tracking App

A modern, self-hosted time tracking application built with Symfony 8.

## Features

- Time tracking for users
- Absence management (vacation, sick leave, etc.)
- Optional half days: half-day holidays (e.g. Christmas Eve), half-day absence requests and quotas in half days
- Approval workflows for requests
- Role-based access control (admin, user)
- Clean and simple UI
- Email notifications for approvals/rejections
- REST API with personal API tokens, Swagger UI and an in-app guide (see below)

## Tech Stack

- PHP >= 8.4
- Symfony 8
- Doctrine ORM
- Twig
- Bootstrap 5
- MySQL / MariaDB / PostgreSQL

## Getting Started

### Requirements

- PHP >= 8.4
- Composer
- Database (MySQL, MariaDB or PostgreSQL)
- Symfony CLI (optional)

### Installation

```bash
git clone https://github.com/oliverfehmel/time-tracking.git
cd time-tracking-app

composer install
```

#### Configure your database in .env:
```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/time_tracking"
```

#### Run migrations:
```bash
php bin/console doctrine:migrations:migrate
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
