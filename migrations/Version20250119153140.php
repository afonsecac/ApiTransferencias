<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250119153140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE user_permission_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE user_permission (id INT NOT NULL, client_id INT DEFAULT NULL, user_info_id INT DEFAULT NULL, item_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_active BOOLEAN NOT NULL, min_role_required VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_472E544619EB6921 ON user_permission (client_id)');
        $this->addSql('CREATE INDEX IDX_472E5446586DFF2 ON user_permission (user_info_id)');
        $this->addSql('CREATE INDEX IDX_472E5446126F525E ON user_permission (item_id)');
        $this->addSql('COMMENT ON COLUMN user_permission.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_permission.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_permission ADD CONSTRAINT FK_472E544619EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_permission ADD CONSTRAINT FK_472E5446586DFF2 FOREIGN KEY (user_info_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_permission ADD CONSTRAINT FK_472E5446126F525E FOREIGN KEY (item_id) REFERENCES navigation_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE user_permission_id_seq CASCADE');
        $this->addSql('ALTER TABLE user_permission DROP CONSTRAINT FK_472E544619EB6921');
        $this->addSql('ALTER TABLE user_permission DROP CONSTRAINT FK_472E5446586DFF2');
        $this->addSql('ALTER TABLE user_permission DROP CONSTRAINT FK_472E5446126F525E');
        $this->addSql('DROP TABLE user_permission');
    }
}
