<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108101504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence_quota (id INT AUTO_INCREMENT NOT NULL, year INT NOT NULL, quota_days INT DEFAULT NULL, user_id INT NOT NULL, absence_type_id INT NOT NULL, INDEX IDX_CDB90F6A76ED395 (user_id), INDEX IDX_CDB90F6CCAA91B (absence_type_id), UNIQUE INDEX uniq_user_type_year (user_id, absence_type_id, year), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE absence_request (id INT AUTO_INCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) NOT NULL, comment LONGTEXT DEFAULT NULL, approved_at DATETIME DEFAULT NULL, reject_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, requested_by_id INT NOT NULL, type_id INT NOT NULL, approved_by_id INT DEFAULT NULL, INDEX IDX_F211AA174DA1E751 (requested_by_id), INDEX IDX_F211AA17C54C8C93 (type_id), INDEX IDX_F211AA172D234F6A (approved_by_id), INDEX idx_absence_status (status), INDEX idx_absence_start (start_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE absence_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, key_name VARCHAR(30) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, requires_approval TINYINT DEFAULT 1 NOT NULL, requires_quota TINYINT DEFAULT 1 NOT NULL, default_yearly_quota_days INT DEFAULT NULL, UNIQUE INDEX UNIQ_FBCF99B65E237E06 (name), UNIQUE INDEX UNIQ_FBCF99B6D824A5CF (key_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE absence_quota ADD CONSTRAINT FK_CDB90F6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE absence_quota ADD CONSTRAINT FK_CDB90F6CCAA91B FOREIGN KEY (absence_type_id) REFERENCES absence_type (id)');
        $this->addSql('ALTER TABLE absence_request ADD CONSTRAINT FK_F211AA174DA1E751 FOREIGN KEY (requested_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE absence_request ADD CONSTRAINT FK_F211AA17C54C8C93 FOREIGN KEY (type_id) REFERENCES absence_type (id)');
        $this->addSql('ALTER TABLE absence_request ADD CONSTRAINT FK_F211AA172D234F6A FOREIGN KEY (approved_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence_quota DROP FOREIGN KEY FK_CDB90F6A76ED395');
        $this->addSql('ALTER TABLE absence_quota DROP FOREIGN KEY FK_CDB90F6CCAA91B');
        $this->addSql('ALTER TABLE absence_request DROP FOREIGN KEY FK_F211AA174DA1E751');
        $this->addSql('ALTER TABLE absence_request DROP FOREIGN KEY FK_F211AA17C54C8C93');
        $this->addSql('ALTER TABLE absence_request DROP FOREIGN KEY FK_F211AA172D234F6A');
        $this->addSql('DROP TABLE absence_quota');
        $this->addSql('DROP TABLE absence_request');
        $this->addSql('DROP TABLE absence_type');
    }
}
