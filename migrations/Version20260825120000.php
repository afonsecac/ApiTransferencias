<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 5 de la deprecación de V1 (frontend, dashboard-cm): borra el menú
 * "Gestión de clientes" (menu.apps.clients.title) y sus 3 hijos — las
 * pantallas `/apps/clients/{prices,packages,contracts}` que servían ya se
 * borraron del frontend. El hijo "Paquetes" resultó tener título
 * `menu.clients.packages.title` (SIN el infijo `.apps.` — inconsistencia
 * real encontrada en BD, no un typo de esta migración; su clave i18n
 * correcta `menu.apps.clients.packages.title` nunca llegó a usarse).
 *
 * Solo "Contratos de precio" tenía migración con down() (Version20260809120300)
 * — "Precios", "Paquetes" y el padre "Gestión de clientes" vivían en la BD
 * desde antes, sin rastro en migraciones. down() reconstruye los 4 items
 * con los valores reales encontrados en dev al momento de escribir esta
 * migración.
 */
final class Version20260825120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Borra el menú V1 "Gestión de clientes" (Precios/Paquetes/Contratos de precio) — pantallas ya retiradas del frontend';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM user_permission
            WHERE item_id IN (
                SELECT id FROM navigation_item
                WHERE title IN (
                    'menu.apps.clients.title',
                    'menu.apps.clients.prices.title',
                    'menu.apps.clients.packages.title',
                    'menu.clients.packages.title',
                    'menu.apps.clients.contracts.title'
                )
            )
        SQL);

        $this->addSql(<<<'SQL'
            DELETE FROM navigation_item
            WHERE title IN (
                'menu.apps.clients.prices.title',
                'menu.apps.clients.packages.title',
                'menu.clients.packages.title',
                'menu.apps.clients.contracts.title'
            )
        SQL);

        $this->addSql("DELETE FROM navigation_item WHERE title = 'menu.apps.clients.title'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (
                id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value
            )
            SELECT
                nextval('navigation_item_id_seq'),
                4,
                'menu.apps.clients.title',
                'collapsable',
                'heroicons_outline:cog-8-tooth',
                '/apps/clients',
                FALSE,
                NOW(),
                NOW(),
                '00135'
            WHERE NOT EXISTS (
                SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.clients.title'
            )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (
                id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value
            )
            SELECT
                nextval('navigation_item_id_seq'),
                parent.id,
                'menu.apps.clients.prices.title',
                'basic',
                'heroicons_outline:banknotes',
                '/apps/clients/prices',
                TRUE,
                NOW(),
                NOW(),
                '00136'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.clients.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.clients.prices.title'
              )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (
                id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value
            )
            SELECT
                nextval('navigation_item_id_seq'),
                parent.id,
                'menu.clients.packages.title',
                'basic',
                'heroicons_outline:inbox-stack',
                '/apps/clients/packages',
                TRUE,
                NOW(),
                NOW(),
                '00137'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.clients.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.clients.packages.title'
              )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (
                id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value
            )
            SELECT
                nextval('navigation_item_id_seq'),
                parent.id,
                'menu.apps.clients.contracts.title',
                'basic',
                'heroicons_outline:document-currency-dollar',
                '/apps/clients/contracts',
                TRUE,
                NOW(),
                NOW(),
                '00138'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.clients.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.clients.contracts.title'
              )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO user_permission (id, item_id, client_id, user_info_id, is_active, min_role_required, created_at, updated_at)
            SELECT
                nextval('user_permission_id_seq'),
                item.id,
                NULL,
                NULL,
                TRUE,
                'ROLE_ADMIN',
                NOW(),
                NOW()
            FROM navigation_item item
            WHERE item.title IN ('menu.apps.clients.title', 'menu.apps.clients.prices.title', 'menu.clients.packages.title', 'menu.apps.clients.contracts.title')
              AND NOT EXISTS (
                  SELECT 1 FROM user_permission up
                  WHERE up.item_id = item.id AND up.min_role_required = 'ROLE_ADMIN' AND up.client_id IS NULL AND up.user_info_id IS NULL
              )
        SQL);
    }
}
