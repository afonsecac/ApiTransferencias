<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Service\Catalog\UpcomingPackageCatalogResolver;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /communication/packages/upcoming — Fase 4 de la deprecación de V1:
 * solo rama V2 (ver docblock de CommunicationClientPackageProvider). Delega
 * en UpcomingPackageCatalogResolver (paquetes V2 con activeStartAt futuro).
 */
class UpcomingPackagesProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UpcomingPackageCatalogResolver $upcomingResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof Account) {
            return [];
        }

        return $this->upcomingResolver->upcomingFor($user);
    }
}
