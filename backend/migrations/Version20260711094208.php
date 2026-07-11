<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dual billing mode + issuer fiscal profile on app_user.
 * billing_mode gets a DEFAULT so existing rows backfill to 'standard' (adding a NOT NULL column without a
 * default to a populated table would fail in production).
 *
 * ES: Modo dual de facturación + perfil fiscal del emisor en app_user. billing_mode lleva DEFAULT para que
 * las filas existentes se rellenen a 'standard' (una columna NOT NULL sin default sobre una tabla con datos
 * rompería en producción).
 */
final class Version20260711094208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add app_user.billing_mode (default standard) + business_name + fiscal_address';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD billing_mode VARCHAR(12) DEFAULT \'standard\' NOT NULL');
        $this->addSql('ALTER TABLE app_user ADD business_name VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD fiscal_address VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP billing_mode');
        $this->addSql('ALTER TABLE app_user DROP business_name');
        $this->addSql('ALTER TABLE app_user DROP fiscal_address');
    }
}
