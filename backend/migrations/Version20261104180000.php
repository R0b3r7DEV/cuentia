<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * E-mail confirmation at sign-up. Every account that already exists is marked confirmed, so nobody is locked
 * out by this change. / Confirmación del email al darse de alta. Las cuentas que ya existen quedan confirmadas.
 */
final class Version20261104180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'app_user: e-mail confirmation (existing accounts confirmed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD email_verification_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE app_user ADD email_verification_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE app_user SET email_verified_at = NOW()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP email_verified_at');
        $this->addSql('ALTER TABLE app_user DROP email_verification_hash');
        $this->addSql('ALTER TABLE app_user DROP email_verification_expires_at');
    }
}
