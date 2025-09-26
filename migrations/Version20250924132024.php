<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250924132024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agency_rubric (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, agency_id INT NOT NULL, INDEX IDX_BC1CC02CCDEADB2A (agency_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE agency_rubric ADD CONSTRAINT FK_BC1CC02CCDEADB2A FOREIGN KEY (agency_id) REFERENCES agency (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE agency_rubric DROP FOREIGN KEY FK_BC1CC02CCDEADB2A');
        $this->addSql('DROP TABLE agency_rubric');
    }
}
