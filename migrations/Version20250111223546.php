<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250111223546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE user_code_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE user_code (id INT NOT NULL, user_info_id INT NOT NULL, code VARCHAR(15) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, invalid_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, email_validated BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D947C5177153098 ON user_code (code)');
        $this->addSql('CREATE INDEX IDX_D947C51586DFF2 ON user_code (user_info_id)');
        $this->addSql('COMMENT ON COLUMN user_code.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_code.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_code.used_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_code.invalid_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE user_code ADD CONSTRAINT FK_D947C51586DFF2 FOREIGN KEY (user_info_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE user_code_id_seq CASCADE');
        $this->addSql('ALTER TABLE user_code DROP CONSTRAINT FK_D947C51586DFF2');
        $this->addSql('DROP TABLE user_code');
    }
}
