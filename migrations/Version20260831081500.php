<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade communication_sale_info.account_identifier — identificador de
 * destino tipo cuenta (p.ej. "usuario@nauta.com.cu"), distinto de
 * phone_number, para productos que lo exigen (Nauta WIFI Recharge exige
 * SOLO este; Nauta Hogar Plus exige este Y phone_number a la vez —
 * confirmado contra el sandbox real de DTOne el 2026-08-31, ver
 * App\Entity\CommunicationProduct::$requiredIdentifierFields). Ver
 * App\Entity\CommunicationSaleRecharge::$accountIdentifier.
 */
final class Version20260831081500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade communication_sale_info.account_identifier (identificador de destino tipo cuenta, p.ej. Nauta)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS account_identifier VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS account_identifier');
    }
}
