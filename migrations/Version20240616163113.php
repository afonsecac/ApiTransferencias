<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240616163113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT FK_DA9CB8D1F44CABFF');
        $this->addSql('ALTER TABLE communication_sale ADD promotion_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_sale ADD CONSTRAINT FK_DA9CB8D1139DF194 FOREIGN KEY (promotion_id) REFERENCES communication_promotions (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_sale ADD CONSTRAINT FK_DA9CB8D1F44CABFF FOREIGN KEY (package_id) REFERENCES communication_client_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_DA9CB8D1139DF194 ON communication_sale (promotion_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT FK_DA9CB8D1139DF194');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT fk_da9cb8d1f44cabff');
        $this->addSql('DROP INDEX IDX_DA9CB8D1139DF194');
        $this->addSql('ALTER TABLE communication_sale DROP promotion_id');
        $this->addSql('ALTER TABLE communication_sale ADD CONSTRAINT fk_da9cb8d1f44cabff FOREIGN KEY (package_id) REFERENCES communication_package (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
