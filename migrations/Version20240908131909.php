<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240908131909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account ADD min_balance DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD critical_balance DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD min_balance DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD critical_balance DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD is_alert BOOLEAN DEFAULT NULL');
        $this->addSql("INSERT INTO sys_config(id, property_name, property_value, created_at, updated_at, is_active) VALUES (2, 'client.min.balance.operation', '300', '2024-09-08 11:25:12', '2024-09-08 11:25:12', true),(3, 'client.critical.balance.operation', '100', '2024-09-08 11:25:12', '2024-09-08 11:25:12', true); ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE client DROP min_balance');
        $this->addSql('ALTER TABLE client DROP critical_balance');
        $this->addSql('ALTER TABLE client DROP currency');
        $this->addSql('ALTER TABLE client DROP is_alert');
        $this->addSql('ALTER TABLE account DROP min_balance');
        $this->addSql('ALTER TABLE account DROP critical_balance');
        $this->addSql('DELETE FROM sys_config WHERE id IN :ids', ['ids' => [2, 3]]);
    }
}
