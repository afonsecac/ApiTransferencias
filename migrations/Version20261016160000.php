<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-287: vínculo usuario–almacén (market_warehouse_user) con los permisos de stock, armado de combos, traslados y entregas.
 * El usuario es un usuario del dashboard (su cuenta, activación y 2FA no cambian); el par (usuario, almacén) es único y el vínculo
 * se desactiva, no se borra. La base exige al menos un permiso.
 *
 * Aditiva y sin efecto: la tabla nace vacía. Reversa (down): borra la tabla.
 */
final class Version20261016160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_warehouse_user (usuarios de un almacén con sus permisos por almacén)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_warehouse_user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_warehouse_user (
                id              INT     NOT NULL DEFAULT nextval('market_warehouse_user_id_seq'),
                user_id         INT     NOT NULL,
                warehouse_id    INT     NOT NULL,
                perm_stock      BOOLEAN NOT NULL DEFAULT FALSE,
                perm_assembly   BOOLEAN NOT NULL DEFAULT FALSE,
                perm_transfers  BOOLEAN NOT NULL DEFAULT FALSE,
                perm_deliveries BOOLEAN NOT NULL DEFAULT FALSE,
                is_active       BOOLEAN NOT NULL DEFAULT TRUE,
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_warehouse_user_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE RESTRICT,
                CONSTRAINT fk_market_warehouse_user_warehouse FOREIGN KEY (warehouse_id) REFERENCES market_warehouse (id) ON DELETE RESTRICT,
                CONSTRAINT ck_market_warehouse_user_permission CHECK (perm_stock OR perm_assembly OR perm_transfers OR perm_deliveries)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_warehouse_user_id_seq OWNED BY market_warehouse_user.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_warehouse_user ON market_warehouse_user (user_id, warehouse_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_warehouse_user_warehouse ON market_warehouse_user (warehouse_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_warehouse_user');
        $this->addSql('DROP SEQUENCE IF EXISTS market_warehouse_user_id_seq');
    }
}
