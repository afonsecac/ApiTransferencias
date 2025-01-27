<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250118030352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE report_marked_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE report_marked (id INT NOT NULL, client_id INT NOT NULL, account_id INT NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_operation_marked INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5FE8403019EB6921 ON report_marked (client_id)');
        $this->addSql('CREATE INDEX IDX_5FE840309B6B5FBA ON report_marked (account_id)');
        $this->addSql('COMMENT ON COLUMN report_marked.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE report_marked ADD CONSTRAINT FK_5FE8403019EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE report_marked ADD CONSTRAINT FK_5FE840309B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE report_marked_id_seq CASCADE');
        $this->addSql('ALTER TABLE report_marked DROP CONSTRAINT FK_5FE8403019EB6921');
        $this->addSql('ALTER TABLE report_marked DROP CONSTRAINT FK_5FE840309B6B5FBA');
        $this->addSql('DROP TABLE report_marked');
    }
}
