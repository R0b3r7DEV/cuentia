<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The CIE follows the CERTINS E model of the GVA: broken-down address, supply source and distributor, specific
 * risks, secondary uses and use specifications, design basis (MTD/project) and its date, insulation resistance
 * and the installer's NIF. Old use values are mapped to the model's list. No invoice data is touched.
 * ES: El CIE sigue el modelo CERTINS E de la GVA. Los usos antiguos se pasan a la lista del modelo. No toca
 * datos de facturas.
 */
final class Version20261103090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'certificate: CERTINS E fields + use values mapped to the model';
    }

    public function up(Schema $schema): void
    {
        foreach (['street_type VARCHAR(20)', 'street_name VARCHAR(150)', 'portal VARCHAR(10)', 'bis VARCHAR(10)', 'stair VARCHAR(10)',
            'floor VARCHAR(10)', 'door VARCHAR(10)', 'population VARCHAR(120)', 'distributor VARCHAR(100)',
            'use_specifications TEXT', 'design_date DATE', 'insulation_resistance VARCHAR(20)', 'installer_nif VARCHAR(20)'] as $col) {
            $this->addSql("ALTER TABLE certificate ADD $col DEFAULT NULL");
        }
        $this->addSql("ALTER TABLE certificate ADD supply_source VARCHAR(20) DEFAULT 'red' NOT NULL");
        $this->addSql("ALTER TABLE certificate ADD design_basis VARCHAR(10) DEFAULT 'mtd' NOT NULL");
        // JSON lists: backfill existing rows with [], then no default (the entity always sets them).
        $this->addSql("ALTER TABLE certificate ADD specific_risks JSON DEFAULT '[]' NOT NULL");
        $this->addSql("ALTER TABLE certificate ADD secondary_uses JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE certificate ALTER specific_risks DROP DEFAULT');
        $this->addSql('ALTER TABLE certificate ALTER secondary_uses DROP DEFAULT');

        // Old use values → the CERTINS E list (unmappable ones are cleared to be chosen again).
        $this->addSql("UPDATE certificate SET use_type = 'industria' WHERE use_type = 'industrial'");
        $this->addSql("UPDATE certificate SET use_type = 'garaje_privado' WHERE use_type = 'garaje'");
        $this->addSql("UPDATE certificate SET use_type = 'servicios_generales' WHERE use_type = 'comunes'");
        $this->addSql("UPDATE certificate SET use_type = 'bombas' WHERE use_type = 'agricola'");
        $this->addSql("UPDATE certificate SET use_type = NULL WHERE use_type IN ('local', 'otros')");
    }

    public function down(Schema $schema): void
    {
        foreach (['street_type', 'street_name', 'portal', 'bis', 'stair', 'floor', 'door', 'population', 'distributor', 'use_specifications',
            'design_date', 'insulation_resistance', 'installer_nif', 'supply_source', 'design_basis', 'specific_risks', 'secondary_uses'] as $col) {
            $this->addSql("ALTER TABLE certificate DROP $col");
        }
    }
}
