<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240221201400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balance_operation DROP CONSTRAINT fk_d2cb95504926c850');
        $this->addSql('ALTER TABLE balance_operation DROP CONSTRAINT fk_d2cb95504a7e4868');
        $this->addSql('DROP INDEX idx_d2cb95504a7e4868');
        $this->addSql('DROP INDEX idx_d2cb95504926c850');
        $this->addSql('ALTER TABLE balance_operation ADD communication_sale_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_operation ADD disabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_operation DROP recharge_id');
        $this->addSql('ALTER TABLE balance_operation DROP sale_id');
        $this->addSql('COMMENT ON COLUMN balance_operation.disabled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE balance_operation ADD CONSTRAINT FK_D2CB95503FAF6132 FOREIGN KEY (communication_sale_id) REFERENCES communication_sale_info (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D2CB95503FAF6132 ON balance_operation (communication_sale_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE balance_operation DROP CONSTRAINT FK_D2CB95503FAF6132');
        $this->addSql('DROP INDEX IDX_D2CB95503FAF6132');
        $this->addSql('ALTER TABLE balance_operation ADD sale_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_operation DROP disabled_at');
        $this->addSql('ALTER TABLE balance_operation RENAME COLUMN communication_sale_id TO recharge_id');
        $this->addSql('ALTER TABLE balance_operation ADD CONSTRAINT fk_d2cb95504926c850 FOREIGN KEY (recharge_id) REFERENCES communication_recharge (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE balance_operation ADD CONSTRAINT fk_d2cb95504a7e4868 FOREIGN KEY (sale_id) REFERENCES communication_sale (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d2cb95504a7e4868 ON balance_operation (sale_id)');
        $this->addSql('CREATE INDEX idx_d2cb95504926c850 ON balance_operation (recharge_id)');
    }
}
