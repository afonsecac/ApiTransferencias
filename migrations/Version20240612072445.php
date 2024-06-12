<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240612072445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE communication_promotions_communication_client_package (communication_promotions_id INT NOT NULL, communication_client_package_id INT NOT NULL, PRIMARY KEY(communication_promotions_id, communication_client_package_id))');
        $this->addSql('CREATE INDEX IDX_22A2D96BD16652A ON communication_promotions_communication_client_package (communication_promotions_id)');
        $this->addSql('CREATE INDEX IDX_22A2D96909B3DC9 ON communication_promotions_communication_client_package (communication_client_package_id)');
        $this->addSql('ALTER TABLE communication_promotions_communication_client_package ADD CONSTRAINT FK_22A2D96BD16652A FOREIGN KEY (communication_promotions_id) REFERENCES communication_promotions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_promotions_communication_client_package ADD CONSTRAINT FK_22A2D96909B3DC9 FOREIGN KEY (communication_client_package_id) REFERENCES communication_client_package (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_promotions_communication_package DROP CONSTRAINT fk_c437bb42bd16652a');
        $this->addSql('ALTER TABLE communication_promotions_communication_package DROP CONSTRAINT fk_c437bb4220f57d45');
        $this->addSql('DROP TABLE communication_promotions_communication_package');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE communication_promotions_communication_package (communication_promotions_id INT NOT NULL, communication_package_id INT NOT NULL, PRIMARY KEY(communication_promotions_id, communication_package_id))');
        $this->addSql('CREATE INDEX idx_c437bb4220f57d45 ON communication_promotions_communication_package (communication_package_id)');
        $this->addSql('CREATE INDEX idx_c437bb42bd16652a ON communication_promotions_communication_package (communication_promotions_id)');
        $this->addSql('ALTER TABLE communication_promotions_communication_package ADD CONSTRAINT fk_c437bb42bd16652a FOREIGN KEY (communication_promotions_id) REFERENCES communication_promotions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_promotions_communication_package ADD CONSTRAINT fk_c437bb4220f57d45 FOREIGN KEY (communication_package_id) REFERENCES communication_package (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE communication_promotions_communication_client_package DROP CONSTRAINT FK_22A2D96BD16652A');
        $this->addSql('ALTER TABLE communication_promotions_communication_client_package DROP CONSTRAINT FK_22A2D96909B3DC9');
        $this->addSql('DROP TABLE communication_promotions_communication_client_package');
    }
}
