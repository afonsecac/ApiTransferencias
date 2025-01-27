<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250112071315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE navigation_item ADD fragment VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD preserve_fragment BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD query_params VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD query_params_handling VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD external_link BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD target VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD exact_match BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD is_active_match_options JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD function VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD classes JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD meta JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE navigation_item ADD disabled BOOLEAN DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE navigation_item DROP fragment');
        $this->addSql('ALTER TABLE navigation_item DROP preserve_fragment');
        $this->addSql('ALTER TABLE navigation_item DROP query_params');
        $this->addSql('ALTER TABLE navigation_item DROP query_params_handling');
        $this->addSql('ALTER TABLE navigation_item DROP external_link');
        $this->addSql('ALTER TABLE navigation_item DROP target');
        $this->addSql('ALTER TABLE navigation_item DROP exact_match');
        $this->addSql('ALTER TABLE navigation_item DROP is_active_match_options');
        $this->addSql('ALTER TABLE navigation_item DROP function');
        $this->addSql('ALTER TABLE navigation_item DROP classes');
        $this->addSql('ALTER TABLE navigation_item DROP meta');
        $this->addSql('ALTER TABLE navigation_item DROP disabled');
    }
}
