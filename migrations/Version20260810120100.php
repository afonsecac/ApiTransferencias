<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2 Fase 1: tabla communication_contract (ver App\Entity\CommunicationContract).
 * El match con communication_package es SIEMPRE por FK
 * (communication_package_id, NOT NULL) — nunca por tupla. destination_amount/
 * destination_currency son un snapshot congelado al crear el contrato, solo
 * para auditoría.
 *
 * `tenant_id IS NULL` = contrato "por defecto" (aplica a cualquier cuenta
 * sin contrato propio para ese paquete). Sin columna is_active: la vigencia
 * es solo start_at/end_at.
 */
final class Version20260810120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea communication_contract (contrato de precio/visibilidad por paquete, V2 Fase 1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS communication_contract_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS communication_contract (
                id                        INT          NOT NULL,
                communication_package_id  INT          NOT NULL,
                tenant_id                 INT              NULL,
                destination_amount        DOUBLE PRECISION NOT NULL,
                destination_currency      VARCHAR(10)  NOT NULL,
                price                     DOUBLE PRECISION NOT NULL,
                currency                  VARCHAR(3)   NOT NULL,
                start_at                  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                end_at                    TIMESTAMP(0) WITHOUT TIME ZONE     NULL,
                created_at                TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at                TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_by_id             INT              NULL,
                PRIMARY KEY (id),
                CONSTRAINT chk_com_contract_price_non_negative CHECK (price >= 0)
            )
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE communication_contract
                ADD CONSTRAINT FK_CC_PACKAGE FOREIGN KEY (communication_package_id) REFERENCES communication_package (id)
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE communication_contract
                ADD CONSTRAINT FK_CC_TENANT FOREIGN KEY (tenant_id) REFERENCES account (id) ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE communication_contract
                ADD CONSTRAINT FK_CC_USER FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_contract_tenant_package
                ON communication_contract (tenant_id, communication_package_id, start_at DESC, end_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_contract_default_package
                ON communication_contract (communication_package_id, start_at DESC, end_at)
                WHERE tenant_id IS NULL
        SQL);
        // PostgreSQL 18: NULLS NOT DISTINCT — como tenant_id puede ser NULL
        // (contrato "por defecto"), sin esto podría haber dos contratos
        // por-defecto abiertos simultáneos del mismo paquete.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_contract_open_per_tenant_package
                ON communication_contract (tenant_id, communication_package_id)
                NULLS NOT DISTINCT
                WHERE end_at IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS communication_contract');
        $this->addSql('DROP SEQUENCE IF EXISTS communication_contract_id_seq');
    }
}
