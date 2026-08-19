<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 6A del rediseño de promociones V2: CommunicationContract pasa de
 * "un contrato → un CommunicationPackage" (FK communication_package_id,
 * NOT NULL) a "un contrato → N CommunicationPackage" (tabla puente
 * communication_contract_package). Un contrato deja de ser "el precio de
 * ESTE paquete" y pasa a ser "el precio para (tenant, destinationAmount,
 * destinationCurrency)" — dos paquetes que comparten esa tupla (ej. un
 * paquete del catálogo normal y uno generado por una promoción V2 al mismo
 * monto) terminan en el MISMO contrato, sin duplicar filas.
 *
 * Migra los datos existentes (cada contrato conserva su único paquete de
 * hoy, ahora vía la tabla puente) antes de tocar la columna vieja.
 *
 * down() es reversible pero con pérdida de información si algún contrato
 * terminó con más de un paquete tras up() + operación normal: solo puede
 * recuperar UN paquete por contrato (el de menor id), porque
 * communication_package_id vuelve a ser una columna singular NOT NULL.
 */
final class Version20260818140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CommunicationContract: de FK singular a ManyToMany con CommunicationPackage (Fase 6A)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS communication_contract_package (
                communication_contract_id INT NOT NULL,
                communication_package_id  INT NOT NULL,
                PRIMARY KEY (communication_contract_id, communication_package_id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE communication_contract_package
                ADD CONSTRAINT FK_CCP_CONTRACT FOREIGN KEY (communication_contract_id) REFERENCES communication_contract (id) ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE communication_contract_package
                ADD CONSTRAINT FK_CCP_PACKAGE FOREIGN KEY (communication_package_id) REFERENCES communication_package (id) ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_contract_package_package
                ON communication_contract_package (communication_package_id)
        SQL);

        // Migrar los datos existentes ANTES de tocar communication_package_id.
        $this->addSql(<<<'SQL'
            INSERT INTO communication_contract_package (communication_contract_id, communication_package_id)
                SELECT id, communication_package_id FROM communication_contract
                ON CONFLICT DO NOTHING
        SQL);

        $this->addSql('DROP INDEX IF EXISTS uniq_com_contract_open_per_tenant_package');
        $this->addSql('DROP INDEX IF EXISTS idx_com_contract_tenant_package');
        $this->addSql('DROP INDEX IF EXISTS idx_com_contract_default_package');
        $this->addSql('ALTER TABLE communication_contract DROP CONSTRAINT IF EXISTS fk_cc_package');
        $this->addSql('ALTER TABLE communication_contract DROP COLUMN IF EXISTS communication_package_id');

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_contract_tenant_amount
                ON communication_contract (tenant_id, destination_amount, destination_currency, start_at DESC, end_at)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_contract_default_amount
                ON communication_contract (destination_amount, destination_currency, start_at DESC, end_at)
                WHERE tenant_id IS NULL
        SQL);
        // PostgreSQL 18: NULLS NOT DISTINCT — como tenant_id puede ser NULL
        // (contrato "por defecto"), sin esto podría haber dos contratos
        // por-defecto abiertos simultáneos de la misma tupla.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_contract_open_per_tenant_amount
                ON communication_contract (tenant_id, destination_amount, destination_currency)
                NULLS NOT DISTINCT
                WHERE end_at IS NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_com_contract_open_per_tenant_amount');
        $this->addSql('DROP INDEX IF EXISTS idx_com_contract_default_amount');
        $this->addSql('DROP INDEX IF EXISTS idx_com_contract_tenant_amount');

        $this->addSql('ALTER TABLE communication_contract ADD COLUMN IF NOT EXISTS communication_package_id INT');

        // Solo puede recuperar UN paquete por contrato (el de menor id) —
        // ver docblock de clase sobre pérdida de información.
        $this->addSql(<<<'SQL'
            UPDATE communication_contract c
                SET communication_package_id = sub.communication_package_id
                FROM (
                    SELECT DISTINCT ON (communication_contract_id) communication_contract_id, communication_package_id
                    FROM communication_contract_package
                    ORDER BY communication_contract_id, communication_package_id ASC
                ) sub
                WHERE c.id = sub.communication_contract_id
        SQL);

        $this->addSql('ALTER TABLE communication_contract ALTER COLUMN communication_package_id SET NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE communication_contract
                ADD CONSTRAINT FK_CC_PACKAGE FOREIGN KEY (communication_package_id) REFERENCES communication_package (id)
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
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_contract_open_per_tenant_package
                ON communication_contract (tenant_id, communication_package_id)
                NULLS NOT DISTINCT
                WHERE end_at IS NULL
        SQL);

        $this->addSql('DROP TABLE IF EXISTS communication_contract_package');
    }
}
