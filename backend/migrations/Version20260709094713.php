<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260709094713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD anthropic_key TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD gocardless_secret_id TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD gocardless_secret_key TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP anthropic_key');
        $this->addSql('ALTER TABLE app_user DROP gocardless_secret_id');
        $this->addSql('ALTER TABLE app_user DROP gocardless_secret_key');
    }
}
