# Time Tracking App

A modern, self-hosted time tracking application built with Symfony 8.

## Features

- Time tracking for users
- Absence management (vacation, sick leave, etc.)
- Approval workflows for requests
- Role-based access control (admin, user)
- Clean and simple UI
- Email notifications for approvals/rejections

## Tech Stack

- PHP 8.5
- Symfony 8
- Doctrine ORM
- Twig
- Bootstrap 5
- MySQL / MariaDB / PostgreSQL

## Getting Started

### Requirements

- PHP >= 8.5
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

## Development

### Run Tests
```bash
php bin/phpunit
```

### Code Quality
```bash
vendor/bin/phpstan analyse
```

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
