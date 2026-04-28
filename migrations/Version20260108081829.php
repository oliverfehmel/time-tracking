<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260108081829 extends AbstractMigration
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
        $this->addSql('CREATE TABLE holiday (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, date DATE NOT NULL, UNIQUE INDEX uniq_holiday_date_name (date, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE holiday_user (holiday_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_EE658930830A3EC0 (holiday_id), INDEX IDX_EE658930A76ED395 (user_id), PRIMARY KEY (holiday_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE holiday_user ADD CONSTRAINT FK_EE658930830A3EC0 FOREIGN KEY (holiday_id) REFERENCES holiday (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE holiday_user ADD CONSTRAINT FK_EE658930A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE holiday_user DROP FOREIGN KEY FK_EE658930830A3EC0');
        $this->addSql('ALTER TABLE holiday_user DROP FOREIGN KEY FK_EE658930A76ED395');
        $this->addSql('DROP TABLE holiday');
        $this->addSql('DROP TABLE holiday_user');
    }
}
