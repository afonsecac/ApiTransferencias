<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231122034305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE environment ADD api_key VARCHAR(255) NOT NULL');
        $this->addSql("INSERT INTO environment (id, environment, created_at, updated_at, url_path, api_key) VALUES (1, 'TEST', '2023-11-22 04:43:30', '2023-11-22 04:43:33', 'https://demo.rebuspay.com', '400b844e-96ec-4aa7-981f-6f1406b6f291')");
        $this->addSql("INSERT INTO environment (id, environment, created_at, updated_at, url_path, api_key) VALUES (2, 'PROD', '2023-11-22 04:43:30', '2023-11-22 04:43:33', 'https://www.rebuspay.com', '400b844e-96ec-4aa7-981f-6f1406b6f291')");
        $this->addSql("INSERT INTO public.client (id, uuid, roles, company_name, company_email, company_phone, company_tel, company_site, company_description, company_legal_representative, created_at, is_active, removed_at, inactive_at, is_accepted_politics, accepted_at, is_accepted_offer, accepted_offer_at, password, origins) VALUES (1, '1', '{}', 'Test', 'email@test.com', '+5356085136', '+5378031560', 'www.sendmundo.com', 'Telecomunicaciones ', 'Alejandro Portela', '2023-11-22 06:03:34', true, null, DEFAULT, true, '2023-11-22 06:03:49', false, '2023-11-22 06:05:13', '1', '*')");
        $this->addSql("INSERT INTO public.permission (id, client_id, environment_id, created_at, token_id, is_active) VALUES (1, 1, 1, '2023-11-22 06:06:49', '662af7d3-6b72-4c28-9074-5571ec607262', true)");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE environment DROP api_key');
    }
}
