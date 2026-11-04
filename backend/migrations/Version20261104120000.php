<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * invoice.paid_at — when the customer paid. Bookkeeping only, outside the Verifactu record and its fingerprint.
 * ES: invoice.paid_at — cuándo pagó el cliente. Solo contabilidad, fuera del registro Verifactu y su huella.
 */
final class Version20261104120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invoice paid date (unpaid invoices for the "Hoy" screen)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice ADD paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP paid_at');
    }
}
