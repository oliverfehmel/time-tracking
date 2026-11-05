---
name: migration
description: Create, review and run Doctrine migrations for this app (MariaDB/MySQL). Use whenever an entity mapping changes, a migration has to be written or fixed, or schema drift is reported.
---

# Doctrine migrations

Migrations live in `migrations/` (namespace `DoctrineMigrations`). Production runs them via `deploy.sh` (`doctrine:migrations:migrate --no-interaction --env=prod`); CI runs on MariaDB 11.8.

## Create

1. Change the entity mapping in `src/Entity`.
2. Make sure the dev database is on the latest version: `php bin/console doctrine:migrations:status`. If migrations are pending, ask before running `doctrine:migrations:migrate` — it changes the user's dev database.
3. Generate: `php bin/console doctrine:migrations:diff`.
4. **Review the generated file by hand** — never commit it unread:
   - Remove statements unrelated to your change. There is known pre-existing drift on the `work_location` indexes; do not sweep it into a feature migration unless the task is to fix it.
   - Check column types: booleans `TINYINT DEFAULT 0 NOT NULL`, day quotas `NUMERIC(5, 1)`; new `NOT NULL` columns on existing tables need a `DEFAULT` or a data backfill, otherwise the migration fails on non-empty tables.
   - Renames must be `CHANGE`/`RENAME`, not drop + add (data loss).

## Required shape

```php
final class VersionYYYYMMDDHHMMSS extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Short, specific description of what changes and why';
    }

    // DDL implicitly commits on MySQL/MariaDB; opt out to avoid Doctrine's deprecation notice.
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void { /* … */ }

    public function down(Schema $schema): void { /* exact reverse of up(), in reverse order */ }
}
```

- `down()` must be implemented and reverse `up()` in reverse order. If a change is genuinely irreversible (data loss), call `$this->throwIrreversibleMigrationException()` and say so in the PR.
- Data migrations (backfilling values) go in the same migration after the DDL, as plain `addSql()` statements — no entity/service usage in migrations.

## Verify

- `php bin/console doctrine:migrations:migrate` then `doctrine:migrations:migrate prev` then `migrate` again (up → down → up) on the dev DB — only with the user's OK, since it touches their data.
- `php bin/console doctrine:schema:validate` — mapping must be in sync except for the known drift mentioned above.
- `php bin/phpunit` (tests use the test DB from `.env.test`).

## Document

Mention every migration in the PR's "Reviewer notes" (what it changes, whether it is reversible, whether it backfills data), and in `CHANGELOG.md` if admins need to know something before deploying.
