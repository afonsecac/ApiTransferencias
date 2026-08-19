<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Account;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationPackageProviderProductRepository;
use App\Service\Pricing\BenefitOperationResolver;
use App\Service\Pricing\PackageCatalogResolver;
use App\Service\Pricing\ResolvedPackageOffer;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Catálogo agnóstico de proveedor (V2) para la app móvil — a diferencia de
 * CommunicationClientPackageProvider (V1), no delega en el provider Doctrine
 * estándar: PackageCatalogResolver::catalogFor() YA decide el conjunto
 * completo de paquetes visibles (no solo su precio), así que no hay nada
 * que "filtrar después" — de ahí que CurrentUserExtension no necesite
 * intervenir en esta operación en absoluto.
 *
 * Excepción: un paquete SIN ningún vínculo explícito paquete→producto
 * (CommunicationPackageProviderProduct) no se muestra aquí, aunque
 * PackageCatalogResolver haya resuelto un precio vía matching automático —
 * la app móvil solo debe ofrecer paquetes que el admin confirmó que
 * despachan de verdad. Este filtro es exclusivo de la vista móvil: el
 * dashboard (preview(), coverage()) sigue usando PackageCatalogResolver sin
 * este recorte, porque ahí el admin necesita ver el paquete PARA vincularlo.
 *
 * Tampoco se muestra un paquete fuera de su propia ventana activa
 * (CommunicationPackage::isActiveAt()) — PackageCatalogResolver::
 * offersFromContracts() no filtra por esto (solo mira si el CONTRATO está
 * vigente, no el paquete), así que un paquete de promoción futura cuyo
 * contrato ya esté vigente (ej. reutilizó el contrato "por defecto" del
 * catálogo normal al mismo monto — ver upsertContract()) podía colarse aquí
 * antes de tiempo. Ese mismo paquete SÍ debe aparecer en /upcoming
 * (UpcomingPackageCatalogResolver), nunca en los dos a la vez.
 */
final class CommunicationPackageCatalogProvider implements ProviderInterface
{
    public function __construct(
        private readonly PackageCatalogResolver $catalogResolver,
        private readonly CommunicationPackageProviderProductRepository $bindingRepo,
        private readonly Security $security,
        private readonly BenefitOperationResolver $benefitResolver,
    ) {
    }

    /**
     * @return list<CommunicationPackage>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $tenant = $this->security->getUser();
        if (!$tenant instanceof Account) {
            return [];
        }

        $now = new \DateTimeImmutable();

        $packages = array_map(
            static fn (ResolvedPackageOffer $offer): CommunicationPackage => $offer->package,
            $this->catalogResolver->catalogFor($tenant, $now),
        );

        $boundIds = $this->bindingRepo->findPackageIdsWithBindings(
            array_map(static fn (CommunicationPackage $p) => (int) $p->getId(), $packages)
        );

        $visible = array_values(array_filter(
            $packages,
            static fn (CommunicationPackage $p) => in_array($p->getId(), $boundIds, true) && $p->isActiveAt($now)
        ));

        // Resuelve `operation` (MULTIPLY/ADD/SET) en vivo contra el estado
        // ACTUAL del catálogo — nunca se persiste (ver BenefitOperationResolver).
        foreach ($visible as $package) {
            $package->setBenefits($this->benefitResolver->resolve($package));
        }

        return $visible;
    }
}
