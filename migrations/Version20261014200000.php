<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Marcas del mercado (COMR-70, T6): tabla market_brand. Una marca pertenece a un vendedor (referenciado, RESTRICT: un
 * vendedor con marcas no se borra) y su identificador (slug) es único entre todas las marcas. No se borran: se desactivan.
 *
 * Aditiva y sin efecto: la tabla nace vacía y nada la usa todavía (las referencias desde el producto llegan después).
 *
 * Reversa (down): borra la tabla (y con ella las marcas).
 */
final class Version20261014200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_brand (marcas del mercado, cada una de un vendedor)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_brand_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_brand (
                id         INT          NOT NULL DEFAULT nextval('market_brand_id_seq'),
                seller_id  INT          NOT NULL,
                slug       VARCHAR(60)  NOT NULL,
                name       VARCHAR(160) NOT NULL,
                is_active  BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_brand_seller FOREIGN KEY (seller_id) REFERENCES market_seller (id) ON DELETE RESTRICT
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_brand_id_seq OWNED BY market_brand.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_brand_slug ON market_brand (slug)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_brand_seller ON market_brand (seller_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_brand');
        $this->addSql('DROP SEQUENCE IF EXISTS market_brand_id_seq');
    }
}
