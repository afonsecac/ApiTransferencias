<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Marca el origen de un CommunicationPackage: nullable = catálogo regular
 * (sincronizado o creado a mano), no nulo = generado por rango para una
 * promoción V2 (ver App\Entity\CommunicationPromotions). Necesario porque
 * CommunicationPackageRepository::findByDestination() (usado por el
 * catálogo normal y por CommunicationContractService::createByRange()) no
 * debe confundir una copia promocional con el paquete regular del mismo
 * monto — dos paquetes pueden compartir tupla destino a propósito (ver
 * docblock de CommunicationPackage).
 */
final class Version20260818090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega communication_package.promotion_id (origen: catálogo regular vs. generado por promoción V2)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE communication_package ADD COLUMN IF NOT EXISTS promotion_id INT DEFAULT NULL');

        $this->addSql(<<<'SQL'
            ALTER TABLE communication_package
                ADD CONSTRAINT FK_CP_PROMOTION FOREIGN KEY (promotion_id) REFERENCES communication_promotions (id) ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_com_package_promotion
                ON communication_package (promotion_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_com_package_promotion');
        $this->addSql('ALTER TABLE communication_package DROP CONSTRAINT IF EXISTS FK_CP_PROMOTION');
        $this->addSql('ALTER TABLE communication_package DROP COLUMN IF EXISTS promotion_id');
    }
}
