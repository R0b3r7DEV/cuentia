<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CUPS of the supply point on the CIE. One new nullable column; no invoice data is touched.
 * ES: CUPS del punto de suministro en el CIE. Una columna nueva nulable; no toca datos de facturas.
 */
final class Version20261102200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'certificate.cups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE certificate ADD cups VARCHAR(22) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE certificate DROP cups');
    }
}
