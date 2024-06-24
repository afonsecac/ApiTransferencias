<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240624004728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE communication_promotions ADD environment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE communication_promotions ADD CONSTRAINT FK_3EA2E68E903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3EA2E68E903E3A94 ON communication_promotions (environment_id)');
        $this->addSql('ALTER TABLE env_auth ALTER token_auth TYPE TEXT');
        $this->addSql('ALTER TABLE env_auth ALTER token_auth TYPE TEXT');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_98029F8D9ADA853B ON env_auth (token_auth)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE communication_promotions DROP CONSTRAINT FK_3EA2E68E903E3A94');
        $this->addSql('DROP INDEX IDX_3EA2E68E903E3A94');
        $this->addSql('ALTER TABLE communication_promotions DROP environment_id');
        $this->addSql('DROP INDEX UNIQ_98029F8D9ADA853B');
        $this->addSql('ALTER TABLE env_auth ALTER token_auth TYPE VARCHAR(4000)');
    }
}
