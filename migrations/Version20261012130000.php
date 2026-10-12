<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repara las ventas rechazadas que quedaron con state_process Pending (COMR-261): state_process pasa a Rejected';
    }

    public function up(Schema $schema): void
    {
        // Solo la incoherencia exacta; es idempotente porque tras la primera pasada ya no hay filas que cumplan la condición.
        $this->addSql("UPDATE communication_sale_info SET state_process = 'Rejected' WHERE state = 'Rejected' AND state_process = 'Pending'");
    }

    public function down(Schema $schema): void
    {
        // Irreversible a propósito: no se puede saber qué filas tenían Pending antes. El valor Pending era el error.
        $this->throwIrreversibleMigrationException('No se restaura state_process Pending en ventas rechazadas.');
    }
}
