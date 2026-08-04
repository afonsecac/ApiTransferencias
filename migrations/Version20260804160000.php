<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Siembra el item de menú "Sync producción -> staging" — pantalla nueva que
 * dispara scripts/sync-prod-to-staging.sh bajo demanda y muestra su estado
 * en vivo. Existe solo cuando el backend corre con DEPLOYMENT_STAGE=production
 * (ver DashboardStagingSyncController::assertProduction()) y el frontend
 * compilado en modo production (environment.production=true) — esta fila
 * puede llegar a existir también en la BD de staging (se sobrescribe
 * mensualmente con la de prod), pero ahí ni el backend ni el frontend la
 * exponen. Mismo criterio de localización por `title` que
 * Version20260804140000 (provider-availability).
 */
final class Version20260804160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Siembra el item de navegación "Sync producción -> staging" (menu.apps.staging-sync.title) y su permiso ROLE_ADMIN';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO navigation_item (
                id, parent_id, title, type, icon, link, is_active, created_at, updated_at, order_value
            )
            SELECT
                nextval('navigation_item_id_seq'),
                parent.id,
                'menu.apps.staging-sync.title',
                'basic',
                'heroicons_outline:circle-stack',
                '/apps/staging-sync',
                TRUE,
                NOW(),
                NOW(),
                '00127'
            FROM navigation_item parent
            WHERE parent.title = 'menu.apps.sys-config.title'
              AND NOT EXISTS (
                  SELECT 1 FROM navigation_item ni WHERE ni.title = 'menu.apps.staging-sync.title'
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
            WHERE item.title = 'menu.apps.staging-sync.title'
              AND NOT EXISTS (
                  SELECT 1 FROM user_permission up
                  WHERE up.item_id = item.id AND up.min_role_required = 'ROLE_ADMIN' AND up.client_id IS NULL AND up.user_info_id IS NULL
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM user_permission
            WHERE item_id IN (SELECT id FROM navigation_item WHERE title = 'menu.apps.staging-sync.title')
        SQL);
        $this->addSql("DELETE FROM navigation_item WHERE title = 'menu.apps.staging-sync.title'");
    }
}
