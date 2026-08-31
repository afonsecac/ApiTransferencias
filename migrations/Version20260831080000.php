<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade communication_product.required_identifier_fields (JSON) —
 * identificador(es) de destino que el proveedor exige para despachar ESE
 * producto: DTOne confirmó contra su sandbox real (2026-08-31) que Nauta
 * WIFI Recharge exige `account_number` (cuenta Nauta), Nauta PLUS exige
 * `mobile_number` y Nauta Hogar Plus exige AMBOS a la vez — mismo
 * `service`/`subservice` (Utilities/Internet o Landline) para los tres, así
 * que no se puede derivar de ahí. Ver ProviderProductDto::$requiredIdentifierFields
 * para el formato (lista de opciones OR, cada una una lista de campos AND) y
 * App\Entity\CommunicationProduct::$requiredIdentifierFields. Default '[]'
 * por compatibilidad con filas históricas — se interpreta como "exigir solo
 * phoneNumber" (comportamiento anterior a este fix).
 */
final class Version20260831080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade communication_product.required_identifier_fields (JSON, default vacío)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE communication_product ADD COLUMN IF NOT EXISTS required_identifier_fields JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_product DROP COLUMN IF EXISTS required_identifier_fields');
    }
}
