<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105102204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add api_token table for personal REST API tokens (hashed, deleted together with their user)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_token (id INT AUTO_INCREMENT NOT NULL, last_used_at DATETIME DEFAULT NULL, name VARCHAR(100) NOT NULL, token_hash VARCHAR(64) NOT NULL, token_prefix VARCHAR(16) NOT NULL, scope VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_api_token_hash (token_hash), INDEX IDX_7BA2F5EBA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE api_token ADD CONSTRAINT FK_7BA2F5EBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_token DROP FOREIGN KEY FK_7BA2F5EBA76ED395');
        $this->addSql('DROP TABLE api_token');
    }
}
