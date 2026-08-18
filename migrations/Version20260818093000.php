<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Afloja communication_promotions.product_id a nullable. Era el "producto
 * de origen" legacy (V1, un único producto de un único proveedor) — las
 * promociones V2 (Fase 5) no tienen ese concepto: cada proveedor resuelve
 * su propia equivalencia por tramo vía CommunicationPackageProviderProduct.
 * Aditivo: las promociones legacy existentes conservan su product_id.
 */
final class Version20260818093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Afloja communication_promotions.product_id a nullable (promociones V2 sin producto de origen)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_promotions ALTER COLUMN product_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_promotions ALTER COLUMN product_id SET NOT NULL');
    }
}
