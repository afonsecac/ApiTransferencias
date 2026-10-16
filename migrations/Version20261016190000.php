<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-276: un lote por rango recuerda de qué valor a qué valor y con qué paso se emitió (mínimo, máximo y paso en unidades
 * menores; nulos en una emisión sin rango). Aditiva e idempotente. Reversa (down): quita las tres columnas.
 */
final class Version20261016190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade a market_gift_card_batch el mínimo, el máximo y el paso con que se emitió un lote por rango';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_gift_card_batch ADD COLUMN IF NOT EXISTS value_min_minor BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE market_gift_card_batch ADD COLUMN IF NOT EXISTS value_max_minor BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE market_gift_card_batch ADD COLUMN IF NOT EXISTS step_minor BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE market_gift_card_batch DROP COLUMN IF EXISTS step_minor');
        $this->addSql('ALTER TABLE market_gift_card_batch DROP COLUMN IF EXISTS value_max_minor');
        $this->addSql('ALTER TABLE market_gift_card_batch DROP COLUMN IF EXISTS value_min_minor');
    }
}
