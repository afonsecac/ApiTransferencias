<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Añade communication_sale_info.access_token — secreto aleatorio único por
 * venta usado para autorizar el comprobante público (GET /api/verify/...),
 * en vez de depender solo del transactionId (predecible/secuencial). Ver
 * App\Entity\CommunicationSaleInfo::$accessToken.
 */
final class Version20261010120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade communication_sale_info.access_token (secreto del comprobante público) y su índice único';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_sale_info ADD COLUMN IF NOT EXISTS access_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS unique_access_token ON communication_sale_info (access_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS unique_access_token');
        $this->addSql('ALTER TABLE communication_sale_info DROP COLUMN IF EXISTS access_token');
    }
}
