<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240908180348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE email_notification_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE email_notification (id INT NOT NULL, balance_in_id INT DEFAULT NULL, account_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, min_info INT DEFAULT NULL, critical_try INT DEFAULT NULL, is_active BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EA4790998E8FCF61 ON email_notification (balance_in_id)');
        $this->addSql('CREATE INDEX IDX_EA4790999B6B5FBA ON email_notification (account_id)');
        $this->addSql('COMMENT ON COLUMN email_notification.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN email_notification.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN email_notification.closed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE email_notification ADD CONSTRAINT FK_EA4790998E8FCF61 FOREIGN KEY (balance_in_id) REFERENCES balance_operation (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE email_notification ADD CONSTRAINT FK_EA4790999B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE email_notification_id_seq CASCADE');
        $this->addSql('ALTER TABLE email_notification DROP CONSTRAINT FK_EA4790998E8FCF61');
        $this->addSql('ALTER TABLE email_notification DROP CONSTRAINT FK_EA4790999B6B5FBA');
        $this->addSql('DROP TABLE email_notification');
    }
}
