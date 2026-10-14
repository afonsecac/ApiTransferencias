<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Unidades organizativas con sus centros de costo (COMR-271). Los centros de costo viven dentro de la unidad
 * (columna JSON) para que el invariante «exactamente un principal» se cumpla en una sola escritura.
 *
 * Aditiva y sin efecto: la tabla nace vacía y nada la usa todavía. Un mismo identificador fiscal no se
 * repite dentro de un país.
 */
final class Version20261014140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_org_unit (unidades organizativas con centros de costo y datos fiscales por país)';
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
                cost_centers   JSON         NOT NULL,
                created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_org_unit_id_seq OWNED BY market_org_unit.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_org_unit_tax_id ON market_org_unit (country_code, tax_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_org_unit');
        $this->addSql('DROP SEQUENCE IF EXISTS market_org_unit_id_seq');
    }
}
