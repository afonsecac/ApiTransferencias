<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-269: relevancia del producto en las búsquedas. relevance_level (0 = sin destacar, hasta 3) y la vigencia por días
 * (relevance_from, relevance_until, ambos incluidos y opcionales). Aditiva: los productos existentes quedan en nivel 0.
 * Un índice parcial sirve el filtro «solo relevantes vigentes».
 *
 * Reversa (down): quita el índice y las tres columnas.
 */
final class Version20261015100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade la relevancia (nivel y vigencia) a market_product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS relevance_level SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS relevance_from DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE market_product ADD COLUMN IF NOT EXISTS relevance_until DATE DEFAULT NULL');
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'ck_market_product_relevance_level') THEN
                    ALTER TABLE market_product ADD CONSTRAINT ck_market_product_relevance_level CHECK (relevance_level BETWEEN 0 AND 3);
                END IF;
            END $$
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_product_relevance ON market_product (relevance_level) WHERE relevance_level > 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_market_product_relevance');
        $this->addSql('ALTER TABLE market_product DROP CONSTRAINT IF EXISTS ck_market_product_relevance_level');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS relevance_until');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS relevance_from');
        $this->addSql('ALTER TABLE market_product DROP COLUMN IF EXISTS relevance_level');
    }
}
