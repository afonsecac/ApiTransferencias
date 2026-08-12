<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2 Fase 1 del reemplazo de paquetes/precios: tabla communication_package
 * (catálogo agnóstico de proveedor — ver App\Entity\CommunicationPackage).
 * Sin tenant/product/environment: solo define cuánto se acredita al
 * destinatario. Quién despacha y a qué precio se vende se resuelven aparte
 * (communication_contract, Fase 2 de servicios de dominio).
 *
 * Guarda de compatibilidad: "communication_package" ya existió como tabla
 * en 2023-2024 con un esquema TOTALMENTE distinto (com_id, com_price,
 * com_package_type, owner_id...) — el precursor de lo que hoy es
 * CommunicationClientPackage/communication_client_package (ver
 * Version20240611161811 y siguientes). Esa tabla vieja quedó reemplazada
 * en su momento y NO existe hoy en ningún entorno real verificado
 * (confirmado contra la BD local, que espeja el dump real de staging:
 * to_regclass('communication_package') IS NULL) — aunque
 * doctrine_migration_versions sigue marcando como aplicada la migración que
 * la (re)creó por última vez (Version20240617080309), sin que ninguna
 * migración posterior la borre explícitamente (probable intervención manual
 * fuera de migraciones, nunca confirmada). Por seguridad se dropea
 * cualquier resto antes de crear la tabla nueva, para que esta migración
 * sea 100% idempotente sin importar el estado real de un entorno no
 * verificado.
 */
final class Version20260810120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea communication_package (catálogo agnóstico de proveedor, V2 Fase 1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS communication_package CASCADE');

        $this->addSql('CREATE SEQUENCE IF NOT EXISTS communication_package_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS communication_package (
                id                   INT          NOT NULL,
                name                 VARCHAR(255) NOT NULL,
                description          VARCHAR(255) NOT NULL,
                know_more            VARCHAR(500)     NULL,
                benefits             JSON         NOT NULL,
                tags                 JSON         NOT NULL,
                service              JSON         NOT NULL,
                validity             JSON             NULL,
                destination_amount   DOUBLE PRECISION NOT NULL,
                destination_currency VARCHAR(10)  NOT NULL,
                is_active            BOOLEAN      NOT NULL DEFAULT TRUE,
                active_start_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                active_end_at        TIMESTAMP(0) WITHOUT TIME ZONE     NULL,
                display_order        INT          NOT NULL DEFAULT 0,
                created_at           TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at           TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT chk_com_package_destination_amount_positive CHECK (destination_amount > 0)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_package_destination
                ON communication_package (destination_currency, destination_amount)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_package_catalog
                ON communication_package (active_start_at, active_end_at, display_order)
                WHERE is_active = TRUE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS communication_package CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS communication_package_id_seq');
    }
}
