<?php

namespace App\Service\Catalog;

use App\Entity\Account;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Repository\CommunicationContractRepository;
use App\Repository\CommunicationPackageRepository;
use App\Service\Pricing\BenefitOperationResolver;
use App\Service\Pricing\PackageOfferSourceEnum;
use App\Service\Pricing\ResolvedPackageOffer;

/**
 * `/communication/packages/upcoming` para cuentas V2 — paquetes que
 * entrarán en vigencia a futuro (ver
 * CommunicationPackageRepository::findUpcoming()), con un precio de
 * preview inyectado.
 *
 * No se usa PackageCatalogResolver aquí a propósito: su precedencia exige
 * contrato VIGENTE (isActiveAt(now)), y el contrato de una promoción futura
 * no lo está todavía — nunca lo resolvería. Esto lee directamente
 * CommunicationPackage::getContracts() en un servicio exclusivo del
 * preview, sin tocar la precedencia de PackageCatalogResolver::catalogFor()/
 * offerFor().
 */
class UpcomingPackageCatalogResolver
{
    public function __construct(
        private readonly CommunicationPackageRepository $packageRepository,
        private readonly CommunicationContractRepository $contractRepository,
        private readonly BenefitOperationResolver $benefitResolver,
    ) {
    }

    /**
     * @return list<CommunicationPackage>
     */
    public function upcomingFor(Account $tenant, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        $ownActiveContracts = $this->contractRepository->findActiveForTenant($tenant, $now);

        $packages = [];
        foreach ($this->packageRepository->findUpcoming($now) as $package) {
            // Fuga de prioridad de tenant: si la cuenta ya tiene contrato(s)
            // propio(s) vigente(s) y ninguno cubre este paquete, cuando la
            // promoción arranque su contrato "por defecto" NO le va a ganar
            // al contrato propio (ver PackageCatalogResolver::catalogFor())
            // — no tiene sentido mostrar un preview de algo que después no
            // le va a aparecer.
            if ($ownActiveContracts !== [] && !$this->coveredByAnyContract($package, $ownActiveContracts)) {
                continue;
            }

            $contract = $this->previewContractFor($package, $tenant);
            if ($contract === null) {
                continue;
            }

            $package->setResolvedOffer(new ResolvedPackageOffer(
                package: $package,
                price: (float) $contract->getPrice(),
                currency: (string) $contract->getCurrency(),
                source: PackageOfferSourceEnum::PROMOTION,
                contractId: $contract->getId(),
                note: 'Preview: contrato aún no vigente (paquete futuro).',
            ));
            // Resuelve `operation` (MULTIPLY/ADD/SET) en vivo — nunca se
            // persiste, ver BenefitOperationResolver.
            $package->setBenefits($this->benefitResolver->resolve($package));

            $packages[] = $package;
        }

        return $packages;
    }

    /**
     * @param list<CommunicationContract> $contracts
     */
    private function coveredByAnyContract(CommunicationPackage $package, array $contracts): bool
    {
        foreach ($contracts as $contract) {
            if ($contract->getPackages()->contains($package)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Contrato a mostrar como preview: preferir uno del propio tenant, si
     * no hay, uno "por defecto" (tenant NULL); dentro de cada grupo, el de
     * startAt más temprano. Desde la Fase 6 (ManyToMany) un paquete puede
     * colgar de más de un contrato a la vez.
     */
    private function previewContractFor(CommunicationPackage $package, Account $tenant): ?CommunicationContract
    {
        $ownContract = null;
        $defaultContract = null;

        foreach ($package->getContracts() as $contract) {
            $contractTenant = $contract->getTenant();
            if ($contractTenant !== null && $contractTenant->getId() === $tenant->getId()) {
                if ($ownContract === null || $contract->getStartAt() < $ownContract->getStartAt()) {
                    $ownContract = $contract;
                }
            } elseif ($contractTenant === null) {
                if ($defaultContract === null || $contract->getStartAt() < $defaultContract->getStartAt()) {
                    $defaultContract = $contract;
                }
            }
        }

        return $ownContract ?? $defaultContract;
    }
}
