<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240110213809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE balance_operation_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE balance_operation (id INT NOT NULL, recharge_id INT DEFAULT NULL, sale_id INT DEFAULT NULL, transfer_id INT DEFAULT NULL, tenant_id INT NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, amount_tax DOUBLE PRECISION NOT NULL, currency_tax VARCHAR(3) NOT NULL, discount DOUBLE PRECISION NOT NULL, currency_discount VARCHAR(3) NOT NULL, total_amount DOUBLE PRECISION NOT NULL, total_currency VARCHAR(3) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, state VARCHAR(10) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D2CB95509033212A ON balance_operation (tenant_id)');
        $this->addSql('CREATE INDEX IDX_D2CB95504926C850 ON balance_operation (recharge_id)');
        $this->addSql('CREATE INDEX IDX_D2CB95504A7E4868 ON balance_operation (sale_id)');
        $this->addSql('CREATE INDEX IDX_D2CB9550537048AF ON balance_operation (transfer_id)');
        $this->addSql('COMMENT ON COLUMN balance_operation.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN balance_operation.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE balance_operation ADD CONSTRAINT FK_D2CB95509033212A FOREIGN KEY (tenant_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE balance_operation ADD CONSTRAINT FK_D2CB95504926C850 FOREIGN KEY (recharge_id) REFERENCES communication_recharge (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE balance_operation ADD CONSTRAINT FK_D2CB95504A7E4868 FOREIGN KEY (sale_id) REFERENCES communication_sale (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE balance_operation ADD CONSTRAINT FK_D2CB9550537048AF FOREIGN KEY (transfer_id) REFERENCES transfer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE account_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE balance_operation_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE bank_card_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE beneficiary_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE city_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE client_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_nationality_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_office_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_package_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_provinces_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_recharge_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE communication_sale_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE country_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE env_auth_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE environment_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE province_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sender_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sys_config_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE transfer_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE "user_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE user_password_id_seq CASCADE');
        $this->addSql('ALTER TABLE account DROP CONSTRAINT FK_7D3656A4903E3A94');
        $this->addSql('ALTER TABLE account DROP CONSTRAINT FK_7D3656A419EB6921');
        $this->addSql('ALTER TABLE balance_operation DROP CONSTRAINT FK_D2CB95509033212A');
        $this->addSql('ALTER TABLE balance_operation DROP CONSTRAINT FK_D2CB95504926C850');
        $this->addSql('ALTER TABLE balance_operation DROP CONSTRAINT FK_D2CB95504A7E4868');
        $this->addSql('ALTER TABLE balance_operation DROP CONSTRAINT FK_D2CB9550537048AF');
        $this->addSql('ALTER TABLE bank_card DROP CONSTRAINT FK_BC74CA5DECCAAFA0');
        $this->addSql('ALTER TABLE beneficiary DROP CONSTRAINT FK_7ABF446A8BAC62AF');
        $this->addSql('ALTER TABLE beneficiary DROP CONSTRAINT FK_7ABF446A9033212A');
        $this->addSql('ALTER TABLE beneficiary DROP CONSTRAINT FK_7ABF446A903E3A94');
        $this->addSql('ALTER TABLE city DROP CONSTRAINT FK_2D5B0234903E3A94');
        $this->addSql('ALTER TABLE city DROP CONSTRAINT FK_2D5B0234E946114A');
        $this->addSql('ALTER TABLE communication_nationality DROP CONSTRAINT FK_A1A56834903E3A94');
        $this->addSql('ALTER TABLE communication_office DROP CONSTRAINT FK_CFDBADC0E946114A');
        $this->addSql('ALTER TABLE communication_office DROP CONSTRAINT FK_CFDBADC0903E3A94');
        $this->addSql('ALTER TABLE communication_package DROP CONSTRAINT FK_ABB94ECF903E3A94');
        $this->addSql('ALTER TABLE communication_provinces DROP CONSTRAINT FK_8E709434903E3A94');
        $this->addSql('ALTER TABLE communication_recharge DROP CONSTRAINT FK_3DBB46929033212A');
        $this->addSql('ALTER TABLE communication_recharge DROP CONSTRAINT FK_3DBB4692F44CABFF');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT FK_DA9CB8D19033212A');
        $this->addSql('ALTER TABLE communication_sale DROP CONSTRAINT FK_DA9CB8D1F44CABFF');
        $this->addSql('ALTER TABLE country DROP CONSTRAINT FK_5373C966903E3A94');
        $this->addSql('ALTER TABLE env_auth DROP CONSTRAINT FK_98029F8DFED90CCA');
        $this->addSql('ALTER TABLE province DROP CONSTRAINT FK_4ADAD40BF92F3E70');
        $this->addSql('ALTER TABLE province DROP CONSTRAINT FK_4ADAD40B903E3A94');
        $this->addSql('ALTER TABLE sender DROP CONSTRAINT FK_5F004ACF9033212A');
        $this->addSql('ALTER TABLE transfer DROP CONSTRAINT FK_4034A3C09033212A');
        $this->addSql('ALTER TABLE transfer DROP CONSTRAINT FK_4034A3C0F624B39D');
        $this->addSql('ALTER TABLE transfer DROP CONSTRAINT FK_4034A3C0ECCAAFA0');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649979B1AD6');
        $this->addSql('ALTER TABLE user_password DROP CONSTRAINT FK_D54FA2D5FBE881F1');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE balance_operation');
        $this->addSql('DROP TABLE bank_card');
        $this->addSql('DROP TABLE beneficiary');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE communication_nationality');
        $this->addSql('DROP TABLE communication_office');
        $this->addSql('DROP TABLE communication_package');
        $this->addSql('DROP TABLE communication_provinces');
        $this->addSql('DROP TABLE communication_recharge');
        $this->addSql('DROP TABLE communication_sale');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE env_auth');
        $this->addSql('DROP TABLE environment');
        $this->addSql('DROP TABLE province');
        $this->addSql('DROP TABLE sender');
        $this->addSql('DROP TABLE sys_config');
        $this->addSql('DROP TABLE transfer');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE user_password');
    }
}
