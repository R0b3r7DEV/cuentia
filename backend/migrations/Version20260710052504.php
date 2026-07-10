<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Store the scanned floor plan drawn under the 2D editor (data URI + its placement in metres).
 * Nullable on purpose: adding a NOT NULL column to a table that already has rows breaks in production.
 *
 * ES: Guarda el plano escaneado que se dibuja bajo el editor 2D (data URI + su encaje en metros).
 * Nullable a propósito: añadir una columna NOT NULL a una tabla con filas rompe en producción.
 */
final class Version20260710052504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add installation.background (scanned floor plan under the 2D editor)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE installation ADD background JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE installation DROP background');
    }
}
