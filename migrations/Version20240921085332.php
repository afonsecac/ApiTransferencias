<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240921085332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE user_session_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE user_session (id INT NOT NULL, user_by_session_id INT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, origin_ip VARCHAR(50) NOT NULL, another_info JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8849CBDE5F4592E9 ON user_session (user_by_session_id)');
        $this->addSql('COMMENT ON COLUMN user_session.created_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_session.updated_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('COMMENT ON COLUMN user_session.closed_at IS \'(DC2Type:datetimetz_immutable)\'');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_8849CBDE5F4592E9 FOREIGN KEY (user_by_session_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE user_session_id_seq CASCADE');
        $this->addSql('ALTER TABLE user_session DROP CONSTRAINT FK_8849CBDE5F4592E9');
        $this->addSql('DROP TABLE user_session');
    }
}
