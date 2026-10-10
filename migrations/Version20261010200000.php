<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite communication_sale_info.provider NULL: ventas aceptadas sin proveedor disponible (stateProcess=AWAITING_PROVIDER) lo reciben al resolverse';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_sale_info ALTER COLUMN provider DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Falla (a propósito) si quedan ventas esperando proveedor: no se
        // puede volver a NOT NULL sin asignarles uno antes.
        $this->addSql('ALTER TABLE communication_sale_info ALTER COLUMN provider SET NOT NULL');
    }
}
