<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Unidades organizativas y sus centros de costo (COMR-271). Cada centro es una fila (para poder buscarlos y
 * hacer reportes por ellos) con su código único dentro de la unidad y, como mucho, un principal por unidad:
 * una restricción de exclusión DIFERIDA, que se comprueba al confirmar la transacción, así cambiar de
 * principal (apagar uno y encender otro) en una sola transacción es válido.
 *
 * Aditiva y sin efecto: las tablas nacen vacías y nada las usa todavía. Un mismo identificador fiscal no se
 * repite dentro de un país.
 */
final class Version20261014140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_org_unit y market_cost_center (unidades organizativas con sus centros de costo y datos fiscales por país)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_org_unit_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_org_unit (
                id             INT          NOT NULL DEFAULT nextval('market_org_unit_id_seq'),
                name           VARCHAR(160) NOT NULL,
                legal_name     VARCHAR(200) NOT NULL,
                country_code   VARCHAR(2)   NOT NULL,
                tax_id         VARCHAR(60)  NOT NULL,
                address        VARCHAR(300) NOT NULL,
                billing_email  VARCHAR(180) NOT NULL,
                types          JSON         NOT NULL,
                fiscal_data    JSON         NOT NULL,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_org_unit_id_seq OWNED BY market_org_unit.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_org_unit_tax_id ON market_org_unit (country_code, tax_id)');

        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_cost_center_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_cost_center (
                id           INT          NOT NULL DEFAULT nextval('market_cost_center_id_seq'),
                org_unit_id  INT          NOT NULL,
                code         VARCHAR(30)  NOT NULL,
                name         VARCHAR(120) NOT NULL,
                principal    BOOLEAN      NOT NULL DEFAULT FALSE,
                active       BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_cost_center_org_unit FOREIGN KEY (org_unit_id) REFERENCES market_org_unit (id) ON DELETE RESTRICT,
                CONSTRAINT ex_market_cost_center_one_principal EXCLUDE USING btree (org_unit_id WITH =) WHERE (principal) DEFERRABLE INITIALLY DEFERRED
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_cost_center_id_seq OWNED BY market_cost_center.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_cost_center_code ON market_cost_center (org_unit_id, code)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_cost_center_code ON market_cost_center (code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_cost_center');
        $this->addSql('DROP SEQUENCE IF EXISTS market_cost_center_id_seq');
        $this->addSql('DROP TABLE IF EXISTS market_org_unit');
        $this->addSql('DROP SEQUENCE IF EXISTS market_org_unit_id_seq');
    }
}
