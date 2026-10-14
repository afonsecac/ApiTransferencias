<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Categorías del mercado (COMR-70, T6): tabla market_category, un árbol con `parent_id` (sin ruta materializada, COMR-174) y
 * nombres en español (obligatorio) e inglés. El identificador (slug) es único; el padre debe existir y no se puede borrar
 * mientras tenga hijas (RESTRICT); una categoría no es su propio padre (CHECK). Que el árbol pase de tres niveles o forme un
 * ciclo lo valida el dominio, no la base. No se borran: se desactivan.
 *
 * Aditiva y sin efecto: la tabla nace vacía y nada la usa todavía (las referencias desde el producto llegan después).
 *
 * Reversa (down): borra la tabla (y con ella las categorías).
 */
final class Version20261014210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_category (categorías del mercado en árbol, con nombres en español e inglés)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_category_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_category (
                id         INT          NOT NULL DEFAULT nextval('market_category_id_seq'),
                slug       VARCHAR(60)  NOT NULL,
                parent_id  INT          DEFAULT NULL,
                name_es    VARCHAR(120) NOT NULL,
                name_en    VARCHAR(120) DEFAULT NULL,
                position   INT          NOT NULL DEFAULT 0,
                is_active  BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_category_parent FOREIGN KEY (parent_id) REFERENCES market_category (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_category_not_own_parent CHECK (parent_id IS NULL OR parent_id <> id),
                CONSTRAINT ck_market_category_position CHECK (position >= 0)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_category_id_seq OWNED BY market_category.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_category_slug ON market_category (slug)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_category_parent ON market_category (parent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_category');
        $this->addSql('DROP SEQUENCE IF EXISTS market_category_id_seq');
    }
}
