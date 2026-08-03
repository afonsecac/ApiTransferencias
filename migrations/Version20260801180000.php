<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Histórico de tasas de cambio (Frankfurter, base EUR — ver
 * CurrencyExchangeRateSyncService). La conversión de moneda en
 * ClientCatalogImportService NUNCA llama a Frankfurter en el momento de la
 * importación: lee la fila más reciente ya guardada aquí. Así una
 * importación de catálogo no depende de que el servicio de cambio esté
 * disponible justo en ese instante — solo depende de que el comando
 * programado (app:provider:sync-exchange-rates) haya corrido en algún
 * momento anterior.
 *
 * Es histórico deliberadamente (no un upsert de "última tasa"): cada
 * corrida del comando inserta filas nuevas para la fecha de referencia que
 * reporta Frankfurter, sin borrar las anteriores — permite auditar qué tasa
 * se usó para una conversión pasada. El índice único (base, target,
 * rate_date) hace idempotente correr el comando varias veces el mismo día
 * hábil (Frankfurter solo publica una tasa por día hábil).
 */
final class Version20260801180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea currency_exchange_rate (histórico de tasas de cambio, base EUR)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS currency_exchange_rate_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS currency_exchange_rate (
                id               INT              NOT NULL,
                base_currency    VARCHAR(3)       NOT NULL,
                target_currency  VARCHAR(3)       NOT NULL,
                rate             DOUBLE PRECISION NOT NULL,
                rate_date        DATE             NOT NULL,
                fetched_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_currency_exchange_rate_scope
                ON currency_exchange_rate (base_currency, target_currency, rate_date)
        SQL);

        // La consulta de "tasa más reciente para este par" ordena por
        // rate_date DESC — este índice la sirve directamente.
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_currency_exchange_rate_lookup
                ON currency_exchange_rate (base_currency, target_currency, rate_date DESC)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS currency_exchange_rate');
        $this->addSql('DROP SEQUENCE IF EXISTS currency_exchange_rate_id_seq');
    }
}
