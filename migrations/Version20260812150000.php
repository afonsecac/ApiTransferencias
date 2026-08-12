<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tabla communication_promotion_provider_product (ver
 * App\Entity\CommunicationPromotionProviderProduct) — vínculo explícito
 * promoción→producto por proveedor. `provider` es un código de texto (mismo
 * espacio que communication_product.provider / CommunicationProviderEnum),
 * no una FK. Único por (promoción, proveedor) — un solo producto vinculado
 * por proveedor y promoción.
 */
final class Version20260812150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea communication_promotion_provider_product (vínculo explícito promoción→producto por proveedor)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS communication_promotion_provider_product_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS communication_promotion_provider_product (
                id                          INT          NOT NULL,
                communication_promotion_id  INT          NOT NULL,
                provider                    VARCHAR(20)  NOT NULL,
                product_id                  INT          NOT NULL,
                created_at                  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE communication_promotion_provider_product
                ADD CONSTRAINT FK_CPRPP_PROMOTION FOREIGN KEY (communication_promotion_id) REFERENCES communication_promotions (id) ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE communication_promotion_provider_product
                ADD CONSTRAINT FK_CPRPP_PRODUCT FOREIGN KEY (product_id) REFERENCES communication_product (id) ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_promotion_provider
                ON communication_promotion_provider_product (communication_promotion_id, provider)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS communication_promotion_provider_product');
        $this->addSql('DROP SEQUENCE IF EXISTS communication_promotion_provider_product_id_seq');
    }
}
