<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Repository\CommunicationPackageRepository;
use App\Service\Catalog\CatalogPackageVisibilityResolver;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /communication/packages/{id} — Fase 4 de la deprecación de V1: solo
 * rama V2 (ver docblock de CommunicationClientPackageProvider). El id ya no
 * pertenece al espacio de CommunicationClientPackage — no se puede delegar
 * en el item provider Doctrine decorado (resolvería la clase equivocada,
 * porque $operation sigue apuntando a CommunicationClientPackage), así que
 * se busca el CommunicationPackage directamente por repositorio y se aplica
 * el mismo criterio de visibilidad que /communication/packages/catalog/{id}
 * (CatalogPackageVisibilityResolver).
 */
class CommunicationClientPackageItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CommunicationPackageRepository $packageRepository,
        private readonly CatalogPackageVisibilityResolver $visibility,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $tenant = $this->security->getUser();
        if (!$tenant instanceof Account) {
            return null;
        }

        $id = $uriVariables['id'] ?? null;
        if (!is_numeric($id)) {
            return null;
        }

        $package = $this->packageRepository->find((int) $id);

        return $package === null ? null : $this->visibility->visibleFor($package, $tenant);
    }
}
