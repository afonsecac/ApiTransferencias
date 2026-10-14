<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Registro fiscal por país (COMR-270): qué datos fiscales se piden para facturar y con qué formato.
 * Crea la tabla y la siembra con los cinco países de inicio y el registro genérico (`ZZ`).
 *
 * Los campos propios de cada país son una PROPUESTA pendiente de validar con contabilidad: se
 * ajustan desde la pantalla de administración, sin tocar código.
 */
final class Version20261014130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crea market_fiscal_profile (registro fiscal por país) y siembra Cuba, España, China, Panamá, Estados Unidos y el genérico';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_fiscal_profile (
                country_code VARCHAR(2) NOT NULL,
                name VARCHAR(120) NOT NULL,
                fields JSON NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (country_code)
            )
        SQL);

        foreach ($this->seed() as $code => [$name, $fields]) {
            $this->addSql(
                'INSERT INTO market_fiscal_profile (country_code, name, fields, updated_at) VALUES (?, ?, ?::json, NOW()) ON CONFLICT (country_code) DO NOTHING',
                [$code, $name, json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS market_fiscal_profile');
    }

    /**
     * @return array<string, array{string, list<array<string, mixed>>}>
     */
    private function seed(): array
    {
        return [
            'CU' => ['Cuba', [
                $this->field('nit', 'NIT', true, 1),
                $this->field('reeup', 'Código REEUP (entidades estatales)', false, 2),
                $this->field('cuenta_bancaria', 'Cuenta bancaria', false, 3),
            ]],
            'ES' => ['España', [
                $this->field('nif', 'NIF o CIF', true, 1, '^[A-Za-z0-9]{9}$'),
                $this->field('iva_intracomunitario', 'Número de IVA intracomunitario', false, 2, '^[A-Za-z]{2}[A-Za-z0-9]{8,12}$'),
            ]],
            'CN' => ['China', [
                $this->field('uscc', 'Código de crédito social unificado', true, 1, '^[0-9A-Z]{18}$', 'Son 18 caracteres: números y letras mayúsculas.'),
                $this->field('telefono', 'Teléfono', false, 2),
                $this->field('banco', 'Banco', false, 3),
                $this->field('cuenta_bancaria', 'Cuenta bancaria', false, 4),
            ]],
            'PA' => ['Panamá', [
                $this->field('ruc', 'RUC', true, 1),
                $this->field('dv', 'Dígito verificador', true, 2, '^[0-9]{1,2}$'),
            ]],
            'US' => ['Estados Unidos', [
                $this->field('ein', 'EIN', true, 1, '^[0-9]{2}-?[0-9]{7}$', 'Employer Identification Number: 9 dígitos, con o sin guion.'),
                $this->field('estado', 'Estado', true, 2, '^[A-Z]{2}$', 'Abreviatura de dos letras mayúsculas, por ejemplo NY.'),
                $this->field('formulario_fiscal', 'Formulario fiscal', false, 3, null, 'W-9 si es de EE. UU., W-8BEN-E si es extranjera.', 'select', ['W-9', 'W-8BEN-E']),
            ]],
            'ZZ' => ['Resto de países', []],
        ];
    }

    /**
     * @param list<string> $options
     * @return array<string, mixed>
     */
    private function field(string $key, string $label, bool $required, int $position, ?string $pattern = null, ?string $help = null, string $type = 'text', array $options = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'pattern' => $pattern,
            'helpText' => $help,
            'options' => $options,
            'active' => true,
            'position' => $position,
        ];
    }
}
