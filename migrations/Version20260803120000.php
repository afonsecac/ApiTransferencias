<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade communication_product.benefits y .service (JSON) — se conservan tal
 * cual del payload del proveedor para que ClientCatalogImportService pueda
 * construir el CommunicationClientPackage automáticamente al enrutar hacia
 * un proveedor distinto de ETECSA, sin tener que re-derivar la estructura.
 * Default '[]'/'{}' por compatibilidad con filas históricas.
 */
final class Version20260803120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade communication_product.benefits y .service (JSON, default vacío)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS benefits JSON NOT NULL DEFAULT '[]'");
        $this->addSql("ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS service JSON NOT NULL DEFAULT '{}'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS benefits');
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS service');
    }
}
