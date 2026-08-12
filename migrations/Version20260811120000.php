<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V2: tabla communication_package_provider_product (ver
 * App\Entity\CommunicationPackageProviderProduct) — vínculo explícito
 * paquete→producto por proveedor. `provider` es un código de texto (mismo
 * espacio que communication_product.provider / CommunicationProviderEnum),
 * no una FK: la lista de proveedores se descubre en runtime, así que un
 * proveedor nuevo no exige tocar el esquema. Único por (paquete, proveedor)
 * — un solo producto vinculado por proveedor y paquete.
 */
final class Version20260811120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea communication_package_provider_product (vínculo explícito paquete→producto por proveedor)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS communication_package_provider_product_id_seq INCREMENT BY 1 MINVALUE 1 START 1');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS communication_package_provider_product (
                id                        INT          NOT NULL,
                communication_package_id  INT          NOT NULL,
                provider                  VARCHAR(20)  NOT NULL,
                product_id                INT          NOT NULL,
                created_at                TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE communication_package_provider_product
                ADD CONSTRAINT FK_CPPP_PACKAGE FOREIGN KEY (communication_package_id) REFERENCES communication_package (id) ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE communication_package_provider_product
                ADD CONSTRAINT FK_CPPP_PRODUCT FOREIGN KEY (product_id) REFERENCES communication_product (id) ON DELETE CASCADE
                NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uniq_com_package_provider
                ON communication_package_provider_product (communication_package_id, provider)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS communication_package_provider_product');
        $this->addSql('DROP SEQUENCE IF EXISTS communication_package_provider_product_id_seq');
    }
}
