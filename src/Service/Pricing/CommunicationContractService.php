<?php

namespace App\Service\Pricing;

use App\DTO\CreateCommunicationContractBatchDto;
use App\DTO\CreateCommunicationContractDto;
use App\DTO\CreateCommunicationContractRangeDto;
use App\DTO\UpdateCommunicationContractDto;
use App\Entity\Account;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Entity\Environment;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationPackageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Administración de CommunicationContract (V2) — calcado de
 * PackageContractService en estructura: alta única + alta en lote (misma
 * TargetAccountResolver), ambas idempotentes por (tenant, paquete): una
 * segunda llamada sobre el mismo par ACTUALIZA el contrato ABIERTO existente
 * (endAt IS NULL) en vez de duplicarlo — el índice único
 * uniq_com_contract_open_per_tenant_package lo garantiza a nivel de esquema.
 * "Pausar" (close()) es la única forma de dejar hueco a uno nuevo distinto.
 */
class CommunicationContractService
{
    /**
     * Tope duro de contratos generables en una sola llamada a
     * createByRange() — mismo espíritu que
     * CommunicationPackageAdminService::MAX_BATCH_SIZE.
     */
    private const MAX_RANGE_SIZE = 200;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TargetAccountResolver $targetAccountResolver,
        private readonly Security $security,
        private readonly CommunicationPackageRepository $packageRepository,
    ) {
    }

    /**
     * @throws MyCurrentException
     */
    public function createSingle(CreateCommunicationContractDto $dto): CommunicationContract
    {
        $package = $this->findPackageOrFail((int) $dto->getCommunicationPackageId());
        $tenant = $this->resolveTenantOrFail($dto->getTenantId(), $dto->getEnvironmentId());

        $contract = $this->upsertContract($package, $tenant);
        $this->applyPricing($contract, (float) $dto->getPrice(), (string) $dto->getCurrency(), $dto->getStartAt(), $dto->getEndAt());
        $this->stampCreatedBy($contract);

        $this->em->flush();

        return $contract;
    }

    /**
     * @throws MyCurrentException
     */
    public function createBatch(CreateCommunicationContractBatchDto $dto): ContractBatchResult
    {
        $package = $this->findPackageOrFail((int) $dto->getCommunicationPackageId());

        $environment = $this->em->getRepository(Environment::class)->find($dto->getEnvironmentId());
        if ($environment === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
        }

        $accounts = $this->targetAccountResolver->resolve($environment, $dto->getClients() ?? []);
        if ($accounts === []) {
            return new ContractBatchResult(0, 0, []);
        }

        $created = 0;
        $updated = 0;
        $accountIds = [];

        foreach ($accounts as $account) {
            $isNew = $this->em->getRepository(CommunicationContract::class)->findOneBy([
                'communicationPackage' => $package,
                'tenant' => $account,
                'endAt' => null,
            ]) === null;

            $contract = $this->upsertContract($package, $account);
            $this->applyPricing($contract, (float) $dto->getPrice(), (string) $dto->getCurrency(), $dto->getStartAt(), $dto->getEndAt());
            $this->stampCreatedBy($contract);

            $isNew ? $created++ : $updated++;
            $accountIds[] = $account->getId();
        }

        $this->em->flush();

        return new ContractBatchResult($created, $updated, $accountIds);
    }

    /**
     * Alta en lote por RANGO de montos: para un mismo tenant (o por
     * defecto), crea un contrato por cada CommunicationPackage cuyo
     * destino caiga en [fromAmount, toAmount] saltando de `step` en
     * `step`, con el precio interpolado linealmente entre `priceFrom`
     * (fromAmount) y `priceTo` (toAmount). Un monto sin paquete
     * correspondiente se omite y se reporta — no aborta el alta completa.
     *
     * @throws MyCurrentException
     */
    public function createByRange(CreateCommunicationContractRangeDto $dto): ContractRangeResult
    {
        $from = (float) $dto->getFromAmount();
        $to = (float) $dto->getToAmount();
        $step = (float) $dto->getStep();
        $destinationCurrency = strtoupper((string) $dto->getDestinationCurrency());

        $count = (int) floor(($to - $from) / $step + 1e-9) + 1;
        if ($count > self::MAX_RANGE_SIZE) {
            throw new MyCurrentException(
                'CONTRACT_RANGE_TOO_LARGE',
                sprintf('El rango genera %d contratos; el máximo permitido es %d', $count, self::MAX_RANGE_SIZE),
                422,
            );
        }

        $tenant = $this->resolveTenantOrFail($dto->getTenantId(), $dto->getEnvironmentId());

        $priceFrom = (float) $dto->getPriceFrom();
        $priceTo = (float) $dto->getPriceTo();
        $priceCurrency = strtoupper((string) $dto->getCurrency());
        $span = $to - $from;

        $created = 0;
        $updated = 0;
        $contracts = [];
        $skippedAmounts = [];

        for ($i = 0; $i < $count; $i++) {
            $amount = round($from + $i * $step, 2);

            $package = $this->packageRepository->findByDestination($amount, $destinationCurrency);
            if ($package === null) {
                $skippedAmounts[] = $amount;
                continue;
            }

            $price = $span > 0
                ? round($priceFrom + ($priceTo - $priceFrom) * ($amount - $from) / $span, 2)
                : round($priceFrom, 2);

            $isNew = $this->em->getRepository(CommunicationContract::class)->findOneBy([
                'communicationPackage' => $package,
                'tenant' => $tenant,
                'endAt' => null,
            ]) === null;

            $contract = $this->upsertContract($package, $tenant);
            $this->applyPricing($contract, $price, $priceCurrency, $dto->getStartAt(), $dto->getEndAt());
            $this->stampCreatedBy($contract);

            $isNew ? $created++ : $updated++;
            $contracts[] = $contract;
        }

        $this->em->flush();

        $contractIds = array_map(static fn (CommunicationContract $c) => $c->getId(), $contracts);

        return new ContractRangeResult($created, $updated, count($skippedAmounts), $contractIds, $skippedAmounts);
    }

    /**
     * Variante de createByRange() para promociones V2 (Fase 5B): en vez de
     * resolver cada paquete por (monto, moneda) contra el catálogo regular
     * (findByDestination() excluye a propósito los paquetes promocionales,
     * ver su docblock), recibe DIRECTAMENTE la lista ya generada por
     * CommunicationPackageAdminService::createBatch() — un contrato "por
     * defecto" (tenant=null, catálogo compartido) por cada paquete, con el
     * precio interpolado linealmente entre priceFrom (el de menor
     * destinationAmount) y priceTo (el de mayor).
     *
     * @param list<CommunicationPackage> $packages
     */
    public function createForPromotionPackages(
        array $packages,
        float $priceFrom,
        float $priceTo,
        string $currency,
        ?string $startAt,
        ?string $endAt,
    ): ContractRangeResult {
        if ($packages === []) {
            return new ContractRangeResult(0, 0, 0, [], []);
        }

        $amounts = array_map(static fn (CommunicationPackage $p) => (float) $p->getDestinationAmount(), $packages);
        $from = min($amounts);
        $to = max($amounts);
        $span = $to - $from;
        $priceCurrency = strtoupper($currency);

        $created = 0;
        $updated = 0;
        $contracts = [];

        foreach ($packages as $package) {
            $amount = (float) $package->getDestinationAmount();
            $price = $span > 0
                ? round($priceFrom + ($priceTo - $priceFrom) * ($amount - $from) / $span, 2)
                : round($priceFrom, 2);

            $isNew = $this->em->getRepository(CommunicationContract::class)->findOneBy([
                'communicationPackage' => $package,
                'tenant' => null,
                'endAt' => null,
            ]) === null;

            $contract = $this->upsertContract($package, null);
            $this->applyPricing($contract, $price, $priceCurrency, $startAt, $endAt);
            $this->stampCreatedBy($contract);

            $isNew ? $created++ : $updated++;
            $contracts[] = $contract;
        }

        $this->em->flush();

        $contractIds = array_map(static fn (CommunicationContract $c) => $c->getId(), $contracts);

        return new ContractRangeResult($created, $updated, 0, $contractIds, []);
    }

    /**
     * Edita un contrato existente — siempre uno a uno (a diferencia de
     * createBatch()/createByRange()). El paquete al que apunta el contrato
     * puede reasignarse, pero nunca por id: se referencia por
     * destinationAmount (+ destinationCurrency opcional si además cambia de
     * moneda/unidad), resuelto contra el catálogo vigente — mismo criterio
     * que createByRange().
     *
     * @throws MyCurrentException si se pide un destinationAmount sin ningún
     *   CommunicationPackage correspondiente
     */
    public function update(CommunicationContract $contract, UpdateCommunicationContractDto $dto): CommunicationContract
    {
        if ($dto->getDestinationAmount() !== null) {
            $currency = $dto->getDestinationCurrency() !== null
                ? strtoupper($dto->getDestinationCurrency())
                : (string) $contract->getDestinationCurrency();

            $package = $this->packageRepository->findByDestination((float) $dto->getDestinationAmount(), $currency);
            if ($package === null) {
                throw new MyCurrentException(
                    'COMMUNICATION_PACKAGE_NOT_FOUND',
                    'No existe ningún paquete con ese monto de destino',
                    404,
                );
            }

            if ($package !== $contract->getCommunicationPackage()) {
                // El índice único (tenant, communicationPackage) WHERE endAt
                // IS NULL solo permite un contrato abierto por par — si ya
                // hay uno para el paquete destino, avisar en vez de dejar
                // que el flush() reviente con una violación de integridad.
                $conflict = $this->em->getRepository(CommunicationContract::class)->findOneBy([
                    'communicationPackage' => $package,
                    'tenant' => $contract->getTenant(),
                    'endAt' => null,
                ]);
                if ($conflict !== null && $conflict !== $contract) {
                    throw new MyCurrentException(
                        'COMMUNICATION_CONTRACT_ALREADY_EXISTS',
                        'Ya existe un contrato abierto para ese paquete y cliente',
                        409,
                    );
                }
            }

            $contract->setCommunicationPackage($package);
            $contract->setDestinationAmount((float) $package->getDestinationAmount());
            $contract->setDestinationCurrency((string) $package->getDestinationCurrency());
        }
        if ($dto->getPrice() !== null) {
            $contract->setPrice($dto->getPrice());
        }
        if ($dto->getCurrency() !== null) {
            $contract->setCurrency(strtoupper($dto->getCurrency()));
        }
        if ($dto->getStartAt() !== null) {
            $contract->setStartAt(self::parseUtc($dto->getStartAt()));
        }
        if ($dto->getEndAt() !== null) {
            $contract->setEndAt(self::parseUtc($dto->getEndAt()));
        }

        $this->em->flush();

        return $contract;
    }

    /**
     * "Pausar" un contrato: cierra ahora mismo, deja hueco para uno nuevo
     * (el índice único solo prohíbe dos contratos ABIERTOS a la vez).
     */
    public function close(CommunicationContract $contract): void
    {
        $contract->close();
        $this->em->flush();
    }

    public function delete(CommunicationContract $contract): void
    {
        $this->em->remove($contract);
        $this->em->flush();
    }

    /**
     * Borrado en lote: $tenantId ausente/null = TODOS los contratos del
     * catálogo (sin acotar por entorno). $tenantId presente = solo los del
     * cliente (Client.id, resuelto a su cuenta ACTIVA en $environmentId vía
     * el mismo criterio que createSingle()/createByRange() —
     * resolveTenantOrFail()).
     *
     * @return int cantidad de contratos borrados
     *
     * @throws MyCurrentException
     */
    public function deleteAll(?int $tenantId, ?int $environmentId): int
    {
        $criteria = [];
        if ($tenantId !== null) {
            $criteria['tenant'] = $this->resolveTenantOrFail($tenantId, $environmentId);
        }

        $contracts = $this->em->getRepository(CommunicationContract::class)->findBy($criteria);
        foreach ($contracts as $contract) {
            $this->em->remove($contract);
        }
        $this->em->flush();

        return count($contracts);
    }

    /**
     * @throws MyCurrentException
     */
    private function findPackageOrFail(int $id): CommunicationPackage
    {
        $package = $this->em->getRepository(CommunicationPackage::class)->find($id);
        if ($package === null) {
            throw new MyCurrentException('COMMUNICATION_PACKAGE_NOT_FOUND', 'Communication package not found', 404);
        }

        return $package;
    }

    /**
     * $tenantId es un Client.id (no Account.id) — se resuelve a la cuenta
     * ACTIVA de ese cliente en $environmentId vía TargetAccountResolver,
     * mismo criterio que createBatch(). Antes de este fix, createSingle()/
     * createByRange() buscaban Account::find($tenantId) directamente,
     * confundiendo el espacio de ids de Client con el de Account — el
     * contrato podía terminar apuntando a una cuenta arbitraria (inactiva o
     * de otro entorno) sin relación con el cliente elegido en el formulario.
     *
     * @throws MyCurrentException
     */
    private function resolveTenantOrFail(?int $tenantId, ?int $environmentId): ?Account
    {
        if ($tenantId === null) {
            return null;
        }

        if ($environmentId === null) {
            throw new MyCurrentException('ENVIRONMENT_REQUIRED', 'environmentId is required when tenantId is given', 422);
        }

        $environment = $this->em->getRepository(Environment::class)->find($environmentId);
        if ($environment === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
        }

        $tenant = $this->targetAccountResolver->resolveOne($environment, $tenantId);
        if ($tenant === null) {
            throw new MyCurrentException('TENANT_NOT_FOUND', 'Tenant not found or inactive in the given environment', 404);
        }

        return $tenant;
    }

    /**
     * Idempotente por (communicationPackage, tenant): reutiliza el contrato
     * ABIERTO existente (endAt IS NULL) en vez de duplicar — el único que
     * puede existir a la vez por el índice único de esquema.
     */
    private function upsertContract(CommunicationPackage $package, ?Account $tenant): CommunicationContract
    {
        $existing = $this->em->getRepository(CommunicationContract::class)->findOneBy([
            'communicationPackage' => $package,
            'tenant' => $tenant,
            'endAt' => null,
        ], ['id' => 'DESC']);

        if ($existing !== null) {
            return $existing;
        }

        $contract = (new CommunicationContract())
            ->setCommunicationPackage($package)
            ->setTenant($tenant)
            ->setDestinationAmount((float) $package->getDestinationAmount())
            ->setDestinationCurrency((string) $package->getDestinationCurrency());

        $this->em->persist($contract);

        return $contract;
    }

    private function applyPricing(CommunicationContract $contract, float $price, string $currency, ?string $startAt, ?string $endAt): void
    {
        $contract->setPrice($price);
        $contract->setCurrency(strtoupper($currency));
        if ($startAt !== null) {
            $contract->setStartAt(self::parseUtc($startAt));
        }
        if ($endAt !== null) {
            $contract->setEndAt(self::parseUtc($endAt));
        }
    }

    /**
     * start_at/end_at son columnas `timestamp without time zone`: Doctrine
     * las escribe con `format()` tal cual (sin convertir) y las relee
     * asumiendo el timezone por defecto de PHP (UTC). Si no se normaliza
     * aquí, un ISO con offset (ej. "-04:00" de Cuba) se guarda con esos
     * dígitos literales y al releer se reinterpreta como UTC, desplazando
     * la hora mostrada en el frontend.
     */
    private static function parseUtc(string $iso): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('UTC'));
    }

    private function stampCreatedBy(CommunicationContract $contract): void
    {
        if ($contract->getCreatedBy() !== null) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $contract->setCreatedBy($user);
        }
    }
}
