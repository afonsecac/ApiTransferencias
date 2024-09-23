<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240920211858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE communication_promotions_communication_price_package (communication_promotions_id INT NOT NULL, communication_price_package_id INT NOT NULL, PRIMARY KEY(communication_promotions_id, communication_price_package_id))');
        $this->addSql('CREATE INDEX IDX_74AF2854BD16652A ON communication_promotions_communication_price_package (communication_promotions_id)');
        $this->addSql('CREATE INDEX IDX_74AF285417DA82ED ON communication_promotions_communication_price_package (communication_price_package_id)');
        $this->addSql('ALTER TABLE communication_promotions_communication_price_package ADD CONSTRAINT FK_74AF2854BD16652A FOREIGN KEY (communication_promotions_id) REFERENCES communication_promotions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_promotions_communication_price_package ADD CONSTRAINT FK_74AF285417DA82ED FOREIGN KEY (communication_price_package_id) REFERENCES communication_price_package (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_promotions_communication_price_package DROP CONSTRAINT FK_74AF2854BD16652A');
        $this->addSql('ALTER TABLE communication_promotions_communication_price_package DROP CONSTRAINT FK_74AF285417DA82ED');
        $this->addSql('DROP TABLE communication_promotions_communication_price_package');
    }
}
