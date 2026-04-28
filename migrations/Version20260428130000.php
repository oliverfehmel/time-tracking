<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add inheritable flags for allowing absence requests over the configured limit';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE absence_type ADD allow_over_limit TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE absence_quota ADD allow_over_limit TINYINT(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE absence_quota DROP allow_over_limit');
        $this->addSql('ALTER TABLE absence_type DROP allow_over_limit');
    }
}
