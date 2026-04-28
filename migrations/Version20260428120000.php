<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add work_location_type and work_location tables with default types';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE work_location_type (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(80) NOT NULL,
            key_name VARCHAR(30) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            icon VARCHAR(100) DEFAULT NULL,
            UNIQUE INDEX UNIQ_work_location_type_name (name),
            UNIQUE INDEX UNIQ_work_location_type_key (key_name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE work_location (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            work_location_type_id INT NOT NULL,
            date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            INDEX IDX_work_location_user (user_id),
            INDEX IDX_work_location_type (work_location_type_id),
            UNIQUE INDEX uniq_work_location_user_date (user_id, date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('ALTER TABLE work_location
            ADD CONSTRAINT FK_work_location_user FOREIGN KEY (user_id) REFERENCES user (id),
            ADD CONSTRAINT FK_work_location_type FOREIGN KEY (work_location_type_id) REFERENCES work_location_type (id)
        ');

        $this->addSql("INSERT INTO work_location_type (name, key_name, is_active, is_default, icon) VALUES
            ('Büro', 'office', 1, 1, 'fa-solid fa-building'),
            ('Home Office', 'home_office', 1, 0, 'fa-solid fa-house'),
            ('Geschäftsreise', 'business_trip', 1, 0, 'fa-solid fa-plane')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE work_location DROP FOREIGN KEY FK_work_location_user');
        $this->addSql('ALTER TABLE work_location DROP FOREIGN KEY FK_work_location_type');
        $this->addSql('DROP TABLE work_location');
        $this->addSql('DROP TABLE work_location_type');
    }
}
