<?php

namespace App\Service\Pricing;

use App\Entity\Account;
use App\Entity\Environment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resuelve el conjunto de cuentas objetivo de una operación por lote sobre
 * clientes — extraído de CommunicationPromotionService::createPackagesForPromotion(),
 * que ya implementaba exactamente este criterio: `clientIds` vacío = todas
 * las cuentas activas del entorno; con ids = solo esas. Reutilizado por
 * PackageContractService (modo "rate") para no duplicar la bifurcación.
 */
class TargetAccountResolver
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param int[] $clientIds vacío = todas las cuentas activas del entorno
     * @return list<Account>
     */
    public function resolve(Environment $environment, array $clientIds): array
    {
        $accounts = $this->em->getRepository(Account::class)->findBy([
            'environment' => $environment,
            'isActive' => true,
        ]);

        if (!empty($clientIds)) {
            $accounts = array_filter($accounts, static function (Account $account) use ($clientIds) {
                return in_array($account->getClient()?->getId(), $clientIds, true);
            });
        }

        return array_values($accounts);
    }

    /**
     * Variante de resolve() para un único cliente — altas "single"/"range"
     * que apuntan a un solo tenant en vez de un lote. Mismo criterio: solo
     * cuenta ACTIVA del cliente en ese entorno; null si no existe o está
     * inactiva.
     */
    public function resolveOne(Environment $environment, int $clientId): ?Account
    {
        $accounts = $this->resolve($environment, [$clientId]);

        return $accounts[0] ?? null;
    }
}
