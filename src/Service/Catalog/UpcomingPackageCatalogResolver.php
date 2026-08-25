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
 *
 * Segundo consumidor de la regla "todo o nada" de contratos (encontrado en
 * la revisión de la Fase 2, no en el diseño original) — respeta el MISMO
 * kill switch que PackageCatalogResolver (ContractGatingScopeResolver): con
 * el flag apagado (default), el gateo sigue siendo por tenant completo,
 * sin cambios; con el flag encendido, se evalúa por categoría (ver
 * isGatedAway()).
 */
class UpcomingPackageCatalogResolver
{
    public function __construct(
        private readonly CommunicationPackageRepository $packageRepository,
        private readonly CommunicationContractRepository $contractRepository,
        private readonly BenefitOperationResolver $benefitResolver,
        private readonly ContractGatingScopeResolver $gatingScopeResolver,
    ) {
    }

    /**
     * @return list<CommunicationPackage>
     */
    public function upcomingFor(Account $tenant, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        $ownActiveContracts = $this->contractRepository->findActiveForTenant($tenant, $now);
        $categoryScoped = $this->gatingScopeResolver->isCategoryScoped($tenant);

        $packages = [];
        foreach ($this->packageRepository->findUpcoming($now) as $package) {
            // Fuga de prioridad de tenant: si la cuenta ya tiene contrato(s)
            // propio(s) vigente(s) (de la categoría del paquete, cuando el
            // flag está en "category") y ninguno cubre este paquete, cuando
            // la promoción arranque su contrato "por defecto" NO le va a
            // ganar al contrato propio (ver PackageCatalogResolver::
            // catalogFor()) — no tiene sentido mostrar un preview de algo
            // que después no le va a aparecer.
            if ($this->isGatedAway($package, $ownActiveContracts, $categoryScoped)) {
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
     * Flag apagado (alcance tenant, default): gateado si el tenant tiene
     * CUALQUIER contrato propio vigente y ninguno cubre este paquete —
     * comportamiento histórico, sin cambios.
     *
     * Flag encendido (alcance category): gateado solo si el tenant tiene
     * contrato propio vigente EN LA CATEGORÍA de este paquete y ninguno de
     * esos lo cubre. Sin contrato propio en esta categoría (aunque tenga en
     * otras) → no gateado, cae al contrato "por defecto" o queda sin
     * preview, igual que si el tenant no tuviera contrato en absoluto.
     *
     * @param list<CommunicationContract> $ownActiveContracts
     */
    private function isGatedAway(CommunicationPackage $package, array $ownActiveContracts, bool $categoryScoped): bool
    {
        if ($ownActiveContracts === []) {
            return false;
        }

        if (!$categoryScoped) {
            return !$this->coveredByAnyContract($package, $ownActiveContracts);
        }

        $categoryContracts = array_values(array_filter(
            $ownActiveContracts,
            static fn (CommunicationContract $c) => $c->getServiceKey() === $package->getServiceKey(),
        ));

        if ($categoryContracts === []) {
            return false;
        }

        return !$this->coveredByAnyContract($package, $categoryContracts);
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
