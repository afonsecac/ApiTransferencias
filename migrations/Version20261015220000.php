<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-281: siembra en la configuración del sistema las unidades base de las medidas del catálogo (peso en kilogramos y
 * volumen en litros) y el divisor del peso volumétrico (6000, el estándar IATA). El dominio las lee sin valor por defecto.
 * Aditiva e idempotente: no pisa un valor que ya exista y no toca ningún producto.
 *
 * Reversa (down): borra las tres claves.
 */
final class Version20261015220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Siembra en sys_config las unidades base (kg y l) y el divisor del peso volumétrico (6000) del catálogo del mercado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO sys_config (id, property_name, property_value, created_at, updated_at, is_active, is_encrypted)
            SELECT nextval('sys_config_id_seq'), v.name, v.value, NOW(), NOW(), TRUE, FALSE
            FROM (VALUES
                ('market.measure.weight_base', 'kg'),
                ('market.measure.volume_base', 'l'),
                ('market.measure.volumetric_divisor', '6000')
            ) AS v(name, value)
            WHERE NOT EXISTS (SELECT 1 FROM sys_config sc WHERE sc.property_name = v.name)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM sys_config WHERE property_name IN ('market.measure.weight_base', 'market.measure.volume_base', 'market.measure.volumetric_divisor')");
    }
}
