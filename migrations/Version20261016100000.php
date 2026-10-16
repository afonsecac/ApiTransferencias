<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * COMR-286: catálogo de provincias y municipios de Cuba (market_province, market_municipality), su siembra y la entrada de
 * menú «Provincias y municipios» dentro de Mercado > Organización.
 *
 * Fuente de la división: 15 provincias + «Isla de la Juventud» (municipio especial, de primer nivel) y 168 municipios,
 * contrastada el 2026-10-16 entre Wikipedia («Municipios de Cuba») y EcuRed. NO se pudo contrastar con la ONEI: `onei_code`
 * queda vacío hasta tener la fuente oficial. Nombres canónicos: La Habana del Este, Remedios, Songo-La Maya, Mella,
 * Carlos Manuel de Céspedes.
 *
 * Idempotente: el código propio es la clave; al repetir se refresca el nombre y la provincia, nunca el estado activo.
 * Reversa (down): borra las tablas y el menú.
 */
final class Version20261016100000 extends AbstractMigration
{
    private const MENU = 'menu.apps.market-geography.title';

    /** @var array<string, list<string>> */
    private const DIVISION = [
        'Pinar del Río' => ['Consolación del Sur', 'Guane', 'La Palma', 'Los Palacios', 'Mantua', 'Minas de Matahambre', 'Pinar del Río', 'San Juan y Martínez', 'San Luis', 'Sandino', 'Viñales'],
        'Artemisa' => ['Alquízar', 'Artemisa', 'Bahía Honda', 'Bauta', 'Caimito', 'Candelaria', 'Guanajay', 'Güira de Melena', 'Mariel', 'San Antonio de los Baños', 'San Cristóbal'],
        'La Habana' => ['Arroyo Naranjo', 'Boyeros', 'Centro Habana', 'Cerro', 'Cotorro', 'Diez de Octubre', 'Guanabacoa', 'La Habana del Este', 'La Habana Vieja', 'La Lisa', 'Marianao', 'Playa', 'Plaza de la Revolución', 'Regla', 'San Miguel del Padrón'],
        'Mayabeque' => ['Batabanó', 'Bejucal', 'Güines', 'Jaruco', 'Madruga', 'Melena del Sur', 'Nueva Paz', 'Quivicán', 'San José de las Lajas', 'San Nicolás', 'Santa Cruz del Norte'],
        'Matanzas' => ['Calimete', 'Cárdenas', 'Ciénaga de Zapata', 'Colón', 'Jagüey Grande', 'Jovellanos', 'Limonar', 'Los Arabos', 'Martí', 'Matanzas', 'Pedro Betancourt', 'Perico', 'Unión de Reyes'],
        'Cienfuegos' => ['Abreus', 'Aguada de Pasajeros', 'Cienfuegos', 'Cruces', 'Cumanayagua', 'Lajas', 'Palmira', 'Rodas'],
        'Villa Clara' => ['Caibarién', 'Camajuaní', 'Cifuentes', 'Corralillo', 'Encrucijada', 'Manicaragua', 'Placetas', 'Quemado de Güines', 'Ranchuelo', 'Remedios', 'Sagua la Grande', 'Santa Clara', 'Santo Domingo'],
        'Sancti Spíritus' => ['Cabaiguán', 'Fomento', 'Jatibonico', 'La Sierpe', 'Sancti Spíritus', 'Taguasco', 'Trinidad', 'Yaguajay'],
        'Ciego de Ávila' => ['Baraguá', 'Bolivia', 'Chambas', 'Ciego de Ávila', 'Ciro Redondo', 'Florencia', 'Majagua', 'Morón', 'Primero de Enero', 'Venezuela'],
        'Camagüey' => ['Camagüey', 'Carlos Manuel de Céspedes', 'Esmeralda', 'Florida', 'Guáimaro', 'Jimaguayú', 'Minas', 'Najasa', 'Nuevitas', 'Santa Cruz del Sur', 'Sibanicú', 'Sierra de Cubitas', 'Vertientes'],
        'Las Tunas' => ['Amancio', 'Colombia', 'Jesús Menéndez', 'Jobabo', 'Las Tunas', 'Majibacoa', 'Manatí', 'Puerto Padre'],
        'Holguín' => ['Antilla', 'Báguanos', 'Banes', 'Cacocum', 'Calixto García', 'Cueto', 'Frank País', 'Gibara', 'Holguín', 'Mayarí', 'Moa', 'Rafael Freyre', 'Sagua de Tánamo', 'Urbano Noris'],
        'Granma' => ['Bartolomé Masó', 'Bayamo', 'Buey Arriba', 'Campechuela', 'Cauto Cristo', 'Guisa', 'Jiguaní', 'Manzanillo', 'Media Luna', 'Niquero', 'Pilón', 'Río Cauto', 'Yara'],
        'Santiago de Cuba' => ['Contramaestre', 'Guamá', 'Mella', 'Palma Soriano', 'San Luis', 'Santiago de Cuba', 'Segundo Frente', 'Songo-La Maya', 'Tercer Frente'],
        'Guantánamo' => ['Baracoa', 'Caimanera', 'El Salvador', 'Guantánamo', 'Imías', 'Maisí', 'Manuel Tames', 'Niceto Pérez', 'San Antonio del Sur', 'Yateras'],
        'Isla de la Juventud' => ['Isla de la Juventud'],
    ];

    public function getDescription(): string
    {
        return 'Crea market_province y market_municipality (15 provincias + Isla de la Juventud, 168 municipios), los siembra y añade el menú "Provincias y municipios"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_province_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_province (
                id          INT          NOT NULL DEFAULT nextval('market_province_id_seq'),
                code        VARCHAR(60)  NOT NULL,
                name        VARCHAR(120) NOT NULL,
                search_name VARCHAR(120) NOT NULL,
                is_special  BOOLEAN      NOT NULL DEFAULT FALSE,
                is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_province_id_seq OWNED BY market_province.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_province_code ON market_province (code)');

        $this->addSql('CREATE SEQUENCE IF NOT EXISTS market_municipality_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS market_municipality (
                id          INT          NOT NULL DEFAULT nextval('market_municipality_id_seq'),
                province_id INT          NOT NULL,
                code        VARCHAR(60)  NOT NULL,
                onei_code   VARCHAR(10)  DEFAULT NULL,
                name        VARCHAR(120) NOT NULL,
                search_name VARCHAR(120) NOT NULL,
                is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
                PRIMARY KEY (id),
                CONSTRAINT fk_market_municipality_province FOREIGN KEY (province_id) REFERENCES market_province (id) ON DELETE RESTRICT
            )
        SQL);
        $this->addSql('ALTER SEQUENCE market_municipality_id_seq OWNED BY market_municipality.id');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_market_municipality_code ON market_municipality (code)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_municipality_province ON market_municipality (province_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_market_municipality_search ON market_municipality (search_name)');

        $this->seed();
        $this->menu();
    }

    private function seed(): void
    {
        $taken = [];
        foreach (array_merge(...array_values(self::DIVISION)) as $name) {
            $taken[self::slug($name)] = ($taken[self::slug($name)] ?? 0) + 1;
        }

        foreach (self::DIVISION as $province => $municipalities) {
            $provinceCode = self::slug($province);
            $special = $province === 'Isla de la Juventud' ? 'TRUE' : 'FALSE';
            $this->addSql(sprintf(
                "INSERT INTO market_province (code, name, search_name, is_special) VALUES ('%s', '%s', '%s', %s) ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name, search_name = EXCLUDED.search_name, is_special = EXCLUDED.is_special",
                $provinceCode,
                self::q($province),
                self::search($province),
                $special,
            ));
            foreach ($municipalities as $name) {
                // Un nombre repetido entre provincias (San Luis) se distingue con el código de su provincia.
                $code = $taken[self::slug($name)] > 1 ? self::slug($name) . '-' . $provinceCode : self::slug($name);
                $this->addSql(sprintf(
                    "INSERT INTO market_municipality (province_id, code, name, search_name) SELECT p.id, '%s', '%s', '%s' FROM market_province p WHERE p.code = '%s' ON CONFLICT (code) DO UPDATE SET province_id = EXCLUDED.province_id, name = EXCLUDED.name, search_name = EXCLUDED.search_name",
                    $code,
                    self::q($name),
                    self::search($name),
                    $provinceCode,
                ));
            }
        }
    }

    private function menu(): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value)
            SELECT nextval('navigation_item_id_seq'), parent.id, 'menu.apps.market-geography.title', 'basic',
                   'heroicons_outline:map', '/apps/market-geography', TRUE, NOW(), NOW(), '00170'
            FROM navigation_item parent
            WHERE parent.title = 'menu.market.organization.title'
              AND NOT EXISTS (SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.market-geography.title')
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO user_permission (id, item_id, client_id, user_info_id, is_active, min_role_required, created_at, updated_at)
            SELECT nextval('user_permission_id_seq'), item.id, NULL, NULL, TRUE, 'ROLE_ADMIN', NOW(), NOW()
            FROM navigation_item item
            WHERE item.title = 'menu.apps.market-geography.title'
              AND NOT EXISTS (
                  SELECT 1 FROM user_permission up
                  WHERE up.item_id = item.id AND up.min_role_required = 'ROLE_ADMIN' AND up.client_id IS NULL AND up.user_info_id IS NULL
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM user_permission WHERE item_id IN (SELECT id FROM navigation_item WHERE title = '" . self::MENU . "')");
        $this->addSql("DELETE FROM navigation_item WHERE title = '" . self::MENU . "'");
        $this->addSql('DROP TABLE IF EXISTS market_municipality');
        $this->addSql('DROP TABLE IF EXISTS market_province');
        $this->addSql('DROP SEQUENCE IF EXISTS market_municipality_id_seq');
        $this->addSql('DROP SEQUENCE IF EXISTS market_province_id_seq');
    }

    private static function search(string $text): string
    {
        $plain = mb_strtolower((string) preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $plain));
    }

    private static function slug(string $text): string
    {
        return str_replace(' ', '-', self::search($text));
    }

    private static function q(string $text): string
    {
        return str_replace("'", "''", $text);
    }
}
