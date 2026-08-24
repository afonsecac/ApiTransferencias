<?php

namespace App\Service\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Al routear un cliente a un proveedor nuevo (≠ ETECSA), sincroniza su
 * catálogo (CommunicationProduct, ver CommunicationCatalogSyncService) para
 * que PackageCatalogResolver (V2) tenga insumo fresco de inmediato —
 * MAX+margen resuelve el precio contra CommunicationProduct en vivo en cada
 * consulta, así que no hace falta materializar nada por cuenta. ETECSA sigue
 * con su flujo propio (ya establecido, anterior a este servicio) — no se
 * toca.
 *
 * Fase 5 de la deprecación de V1: hasta antes de esta fase, este servicio
 * también materializaba un CommunicationClientPackage (referencia + copia
 * por cuenta) para cuentas que aún no fueran V2 — se retiró junto con el
 * resto del catálogo V1: `CatalogVersionResolver::isV2()` ya resuelve V2
 * para el 100% de las cuentas, así que esa rama nunca se ejecutaba en la
 * práctica.
 *
 * Best-effort: un fallo al sincronizar (p.ej. proveedor inalcanzable) se
 * loguea y no impide que ProviderRoutingAdminService::create()/update()
 * complete la creación/actualización del routing en sí.
 */
class ClientCatalogImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationCatalogSyncService $catalogSyncService,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    ) {
    }

    public function importForRouting(ClientProviderRouting $routing): void
    {
        $providerCode = CommunicationProviderEnum::tryFrom($routing->getProvider() ?? '');
        if ($providerCode === null || $providerCode === CommunicationProviderEnum::ETECSA) {
            return;
        }

        $client = $routing->getClient();
        if ($client === null) {
            return;
        }

        foreach ($this->resolveEnvironments($client, $routing->getEnvironment()) as $environment) {
            try {
                $this->catalogSyncService->syncProducts($providerCode, $environment);
            } catch (\Throwable $e) {
                $this->providerLogger->error('Import de catálogo: falló el sync de productos, se omite este entorno.', [
                    'provider' => $providerCode->value,
                    'environmentId' => $environment->getId(),
                    'clientId' => $client->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return list<Environment>
     */
    private function resolveEnvironments(Client $client, ?Environment $routingEnvironment): array
    {
        if ($routingEnvironment !== null) {
            return [$routingEnvironment];
        }

        $accounts = $this->em->getRepository(Account::class)->findBy([
            'client' => $client,
            'isActive' => true,
        ]);

        $environments = [];
        foreach ($accounts as $account) {
            $environment = $account->getEnvironment();
            if ($environment !== null) {
                $environments[$environment->getId()] = $environment;
            }
        }

        return array_values($environments);
    }
}
