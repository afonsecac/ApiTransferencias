<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Endurece communication_sale_info.provider a NOT NULL. Version20260801100000
 * ya backfillea a 'ETECSA' cualquier fila histórica con provider NULL, y
 * desde la Fase 1 el único punto de creación de ventas (CommunicationSaleService,
 * vía processReserve/processRecharge/executeSale) siempre asigna un provider
 * antes de persistir — confirmado por grep: no existe ningún otro
 * `new CommunicationSaleRecharge()`/`new CommunicationSalePackage()` en el
 * código. La verificación en datos reales (sincronización mensual prod→staging,
 * restaurada en local) confirma 0 filas con provider NULL sobre 5023 ventas.
 * El UPDATE de esta migración es un cinturón de seguridad idempotente, no la
 * base de la decisión.
 */
final class Version20260801150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'communication_sale_info.provider a NOT NULL (Fase 3, cierre del enrutado multi-proveedor)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE communication_sale_info SET provider = 'ETECSA' WHERE provider IS NULL");
        $this->addSql('ALTER TABLE communication_sale_info ALTER COLUMN provider SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_sale_info ALTER COLUMN provider DROP NOT NULL');
    }
}
