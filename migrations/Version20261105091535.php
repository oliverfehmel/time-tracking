<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105091535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add half-day support for holidays, absence requests and absence quotas (switchable via settings)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings ADD half_days_enabled TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE holiday ADD half_day TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE absence_request ADD half_day TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE absence_quota CHANGE quota_days quota_days NUMERIC(5, 1) DEFAULT NULL');
        $this->addSql('ALTER TABLE absence_type CHANGE default_yearly_quota_days default_yearly_quota_days NUMERIC(5, 1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE absence_type CHANGE default_yearly_quota_days default_yearly_quota_days INT DEFAULT NULL');
        $this->addSql('ALTER TABLE absence_quota CHANGE quota_days quota_days INT DEFAULT NULL');
        $this->addSql('ALTER TABLE absence_request DROP half_day');
        $this->addSql('ALTER TABLE holiday DROP half_day');
        $this->addSql('ALTER TABLE settings DROP half_days_enabled');
    }
}
