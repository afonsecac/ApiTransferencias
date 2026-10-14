<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vincula cada comprador actual (Client) a una unidad organizativa de tipo comprador (COMR-272).
 *
 * - Añade client.org_unit_id (nulo, con FK a market_org_unit).
 * - Por cada Client sin unidad: crea la unidad (país ISO-2 derivado del ISO-3 del Client, identificador fiscal
 *   normalizado, datos fiscales vacíos hasta que se editen) o reutiliza la que ya exista con el mismo país e
 *   identificador fiscal añadiéndole el tipo "buyer"; le da un centro de costo principal "PRINCIPAL" con el
 *   nombre del cliente y deja el Client apuntando a ella. Dos Client con el mismo país e identificador fiscal
 *   comparten unidad.
 * - Si algún Client tiene un país que no se reconoce, aborta con un mensaje que lo nombra, sin inventar nada.
 *
 * Idempotente: solo toca Client con org_unit_id nulo, no duplica unidades (clave país + identificador) ni
 * centros (solo a unidades sin ninguno), y no repite el tipo.
 *
 * Reversa (down): quita la columna y la FK. Las unidades y centros creados se dejan: son datos válidos que
 * pueden estar ya enlazados desde otros sitios, y volver a subir la migración los reutiliza.
 */
final class Version20261014150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vincula cada Client a una unidad organizativa de tipo comprador con su centro de costo principal (client.org_unit_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD COLUMN IF NOT EXISTS org_unit_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_client_org_unit') THEN
                    ALTER TABLE client ADD CONSTRAINT fk_client_org_unit FOREIGN KEY (org_unit_id) REFERENCES market_org_unit (id) ON DELETE RESTRICT;
                END IF;
            END
            $$
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_client_org_unit ON client (org_unit_id)');

        // Tabla de conversión ISO-3 → ISO-2, congelada aquí para que la migración no cambie si cambia el código.
        $this->addSql('DROP TABLE IF EXISTS tmp_country_iso');
        $this->addSql('CREATE TEMPORARY TABLE tmp_country_iso (iso3 VARCHAR(3) PRIMARY KEY, iso2 VARCHAR(2) NOT NULL)');
        $this->addSql(<<<'SQL'
            INSERT INTO tmp_country_iso (iso3, iso2) VALUES
                ('AND', 'AD'), ('ARE', 'AE'), ('AFG', 'AF'), ('ATG', 'AG'), ('AIA', 'AI'), ('ALB', 'AL'), ('ARM', 'AM'), ('AGO', 'AO'),
                ('ATA', 'AQ'), ('ARG', 'AR'), ('ASM', 'AS'), ('AUT', 'AT'), ('AUS', 'AU'), ('ABW', 'AW'), ('ALA', 'AX'), ('AZE', 'AZ'),
                ('BIH', 'BA'), ('BRB', 'BB'), ('BGD', 'BD'), ('BEL', 'BE'), ('BFA', 'BF'), ('BGR', 'BG'), ('BHR', 'BH'), ('BDI', 'BI'),
                ('BEN', 'BJ'), ('BLM', 'BL'), ('BMU', 'BM'), ('BRN', 'BN'), ('BOL', 'BO'), ('BES', 'BQ'), ('BRA', 'BR'), ('BHS', 'BS'),
                ('BTN', 'BT'), ('BVT', 'BV'), ('BWA', 'BW'), ('BLR', 'BY'), ('BLZ', 'BZ'), ('CAN', 'CA'), ('CCK', 'CC'), ('COD', 'CD'),
                ('CAF', 'CF'), ('COG', 'CG'), ('CHE', 'CH'), ('CIV', 'CI'), ('COK', 'CK'), ('CHL', 'CL'), ('CMR', 'CM'), ('CHN', 'CN'),
                ('COL', 'CO'), ('CRI', 'CR'), ('CUB', 'CU'), ('CPV', 'CV'), ('CUW', 'CW'), ('CXR', 'CX'), ('CYP', 'CY'), ('CZE', 'CZ'),
                ('DEU', 'DE'), ('DJI', 'DJ'), ('DNK', 'DK'), ('DMA', 'DM'), ('DOM', 'DO'), ('DZA', 'DZ'), ('ECU', 'EC'), ('EST', 'EE'),
                ('EGY', 'EG'), ('ESH', 'EH'), ('ERI', 'ER'), ('ESP', 'ES'), ('ETH', 'ET'), ('FIN', 'FI'), ('FJI', 'FJ'), ('FLK', 'FK'),
                ('FSM', 'FM'), ('FRO', 'FO'), ('FRA', 'FR'), ('GAB', 'GA'), ('GBR', 'GB'), ('GRD', 'GD'), ('GEO', 'GE'), ('GUF', 'GF'),
                ('GGY', 'GG'), ('GHA', 'GH'), ('GIB', 'GI'), ('GRL', 'GL'), ('GMB', 'GM'), ('GIN', 'GN'), ('GLP', 'GP'), ('GNQ', 'GQ'),
                ('GRC', 'GR'), ('SGS', 'GS'), ('GTM', 'GT'), ('GUM', 'GU'), ('GNB', 'GW'), ('GUY', 'GY'), ('HKG', 'HK'), ('HMD', 'HM'),
                ('HND', 'HN'), ('HRV', 'HR'), ('HTI', 'HT'), ('HUN', 'HU'), ('IDN', 'ID'), ('IRL', 'IE'), ('ISR', 'IL'), ('IMN', 'IM'),
                ('IND', 'IN'), ('IOT', 'IO'), ('IRQ', 'IQ'), ('IRN', 'IR'), ('ISL', 'IS'), ('ITA', 'IT'), ('JEY', 'JE'), ('JAM', 'JM'),
                ('JOR', 'JO'), ('JPN', 'JP'), ('KEN', 'KE'), ('KGZ', 'KG'), ('KHM', 'KH'), ('KIR', 'KI'), ('COM', 'KM'), ('KNA', 'KN'),
                ('PRK', 'KP'), ('KOR', 'KR'), ('KWT', 'KW'), ('CYM', 'KY'), ('KAZ', 'KZ'), ('LAO', 'LA'), ('LBN', 'LB'), ('LCA', 'LC'),
                ('LIE', 'LI'), ('LKA', 'LK'), ('LBR', 'LR'), ('LSO', 'LS'), ('LTU', 'LT'), ('LUX', 'LU'), ('LVA', 'LV'), ('LBY', 'LY'),
                ('MAR', 'MA'), ('MCO', 'MC'), ('MDA', 'MD'), ('MNE', 'ME'), ('MAF', 'MF'), ('MDG', 'MG'), ('MHL', 'MH'), ('MKD', 'MK'),
                ('MLI', 'ML'), ('MMR', 'MM'), ('MNG', 'MN'), ('MAC', 'MO'), ('MNP', 'MP'), ('MTQ', 'MQ'), ('MRT', 'MR'), ('MSR', 'MS'),
                ('MLT', 'MT'), ('MUS', 'MU'), ('MDV', 'MV'), ('MWI', 'MW'), ('MEX', 'MX'), ('MYS', 'MY'), ('MOZ', 'MZ'), ('NAM', 'NA'),
                ('NCL', 'NC'), ('NER', 'NE'), ('NFK', 'NF'), ('NGA', 'NG'), ('NIC', 'NI'), ('NLD', 'NL'), ('NOR', 'NO'), ('NPL', 'NP'),
                ('NRU', 'NR'), ('NIU', 'NU'), ('NZL', 'NZ'), ('OMN', 'OM'), ('PAN', 'PA'), ('PER', 'PE'), ('PYF', 'PF'), ('PNG', 'PG'),
                ('PHL', 'PH'), ('PAK', 'PK'), ('POL', 'PL'), ('SPM', 'PM'), ('PCN', 'PN'), ('PRI', 'PR'), ('PSE', 'PS'), ('PRT', 'PT'),
                ('PLW', 'PW'), ('PRY', 'PY'), ('QAT', 'QA'), ('REU', 'RE'), ('ROU', 'RO'), ('SRB', 'RS'), ('RUS', 'RU'), ('RWA', 'RW'),
                ('SAU', 'SA'), ('SLB', 'SB'), ('SYC', 'SC'), ('SDN', 'SD'), ('SWE', 'SE'), ('SGP', 'SG'), ('SHN', 'SH'), ('SVN', 'SI'),
                ('SJM', 'SJ'), ('SVK', 'SK'), ('SLE', 'SL'), ('SMR', 'SM'), ('SEN', 'SN'), ('SOM', 'SO'), ('SUR', 'SR'), ('SSD', 'SS'),
                ('STP', 'ST'), ('SLV', 'SV'), ('SXM', 'SX'), ('SYR', 'SY'), ('SWZ', 'SZ'), ('TCA', 'TC'), ('TCD', 'TD'), ('ATF', 'TF'),
                ('TGO', 'TG'), ('THA', 'TH'), ('TJK', 'TJ'), ('TKL', 'TK'), ('TLS', 'TL'), ('TKM', 'TM'), ('TUN', 'TN'), ('TON', 'TO'),
                ('TUR', 'TR'), ('TTO', 'TT'), ('TUV', 'TV'), ('TWN', 'TW'), ('TZA', 'TZ'), ('UKR', 'UA'), ('UGA', 'UG'), ('UMI', 'UM'),
                ('USA', 'US'), ('URY', 'UY'), ('UZB', 'UZ'), ('VAT', 'VA'), ('VCT', 'VC'), ('VEN', 'VE'), ('VGB', 'VG'), ('VIR', 'VI'),
                ('VNM', 'VN'), ('VUT', 'VU'), ('WLF', 'WF'), ('WSM', 'WS'), ('XKX', 'XK'), ('YEM', 'YE'), ('MYT', 'YT'), ('ZAF', 'ZA'),
                ('ZMB', 'ZM'), ('ZWE', 'ZW')
        SQL);

        $this->addSql(<<<'SQL'
            DO $$
            DECLARE unknown TEXT;
            BEGIN
                SELECT string_agg(DISTINCT c.company_country, ', ') INTO unknown
                FROM client c
                LEFT JOIN tmp_country_iso m ON m.iso3 = upper(trim(c.company_country))
                WHERE c.org_unit_id IS NULL AND m.iso2 IS NULL;
                IF unknown IS NOT NULL THEN
                    RAISE EXCEPTION 'COMR-272: país de Client no reconocido (ISO-3): %', unknown;
                END IF;
            END
            $$
        SQL);

        // Una unidad por (país, identificador fiscal); si ya existe se respeta.
        $this->addSql(<<<'SQL'
            INSERT INTO market_org_unit (name, legal_name, country_code, tax_id, address, billing_email, types, fiscal_data, created_at, updated_at)
            SELECT DISTINCT ON (m.iso2, upper(trim(c.company_identification)))
                left(c.company_name, 160),
                left(c.company_name, 200),
                m.iso2,
                left(upper(trim(c.company_identification)), 60),
                left(COALESCE(NULLIF(trim(c.company_address), ''), 'Sin dirección registrada'), 300),
                left(c.company_email, 180),
                '["buyer"]',
                '{}',
                NOW(),
                NOW()
            FROM client c
            JOIN tmp_country_iso m ON m.iso3 = upper(trim(c.company_country))
            WHERE c.org_unit_id IS NULL
            ORDER BY m.iso2, upper(trim(c.company_identification)), c.id
            ON CONFLICT (country_code, tax_id) DO NOTHING
        SQL);

        // Las unidades que ya existían ganan el tipo comprador sin perder los suyos.
        $this->addSql(<<<'SQL'
            UPDATE market_org_unit u
            SET types = (u.types::jsonb || '["buyer"]'::jsonb)::json, updated_at = NOW()
            FROM client c
            JOIN tmp_country_iso m ON m.iso3 = upper(trim(c.company_country))
            WHERE c.org_unit_id IS NULL
              AND u.country_code = m.iso2
              AND u.tax_id = upper(trim(c.company_identification))
              AND NOT (u.types::jsonb @> '"buyer"'::jsonb)
        SQL);

        // Centro principal con el nombre del primer cliente de cada unidad que aún no tiene ninguno.
        $this->addSql(<<<'SQL'
            INSERT INTO market_cost_center (org_unit_id, code, name, principal, active, created_at)
            SELECT DISTINCT ON (u.id) u.id, 'PRINCIPAL', left(c.company_name, 120), TRUE, TRUE, NOW()
            FROM client c
            JOIN tmp_country_iso m ON m.iso3 = upper(trim(c.company_country))
            JOIN market_org_unit u ON u.country_code = m.iso2 AND u.tax_id = upper(trim(c.company_identification))
            WHERE c.org_unit_id IS NULL
              AND NOT EXISTS (SELECT 1 FROM market_cost_center cc WHERE cc.org_unit_id = u.id)
            ORDER BY u.id, c.id
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE client c
            SET org_unit_id = u.id
            FROM tmp_country_iso m, market_org_unit u
            WHERE c.org_unit_id IS NULL
              AND m.iso3 = upper(trim(c.company_country))
              AND u.country_code = m.iso2
              AND u.tax_id = upper(trim(c.company_identification))
        SQL);

        $this->addSql('DROP TABLE IF EXISTS tmp_country_iso');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP CONSTRAINT IF EXISTS fk_client_org_unit');
        $this->addSql('DROP INDEX IF EXISTS idx_client_org_unit');
        $this->addSql('ALTER TABLE client DROP COLUMN IF EXISTS org_unit_id');
    }
}
