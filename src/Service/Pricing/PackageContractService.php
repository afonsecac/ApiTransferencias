<?php

namespace App\Service\Pricing;

use App\DTO\CreateFixedContractDto;
use App\DTO\CreateRateContractDto;
use App\Entity\Account;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Service\Provider\CurrencyConversionService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Administración de contratos de precio (CommunicationPricePackage con
 * isContract=true) — la pieza que faltaba tras separar precio de estructura
 * en CommunicationClientPackage. Dos modos, igual que el usuario los
 * describió:
 *  - "precio a precio" (createFixed): el admin fija un importe exacto para
 *    un cliente concreto.
 *  - "rate" (createByRate): el admin fija un precio BASE + una tasa, y el
 *    sistema calcula y aplica el resultado a un lote de clientes (vacío =
 *    todos los activos del entorno, mismo criterio que
 *    CommunicationPromotionService — ver TargetAccountResolver).
 *
 * Ambos modos son idempotentes por (tenant, referencePackage): una segunda
 * llamada sobre el mismo par ACTUALIZA el contrato existente en vez de
 * duplicarlo, igual que ya hacía CommunicationPromotionService con sus
 * CommunicationPricePackage.
 */
class PackageContractService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TargetAccountResolver $targetAccountResolver,
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /**
     * @throws MyCurrentException
     */
    public function createFixed(CreateFixedContractDto $dto): CommunicationPricePackage
    {
        $tenant = $this->em->getRepository(Account::class)->find($dto->getTenantId());
        if ($tenant === null) {
            throw new MyCurrentException('TENANT_NOT_FOUND', 'Tenant not found', 404);
        }

        $reference = $this->findReferenceOrFail((int) $dto->getReferencePackageId());

        $contract = $this->upsertContract($tenant, $reference);
        $contract->setContractMode('FIXED');
        $contract->setAmount((float) $dto->getAmount());
        $contract->setCurrency((string) $dto->getCurrency());
        $contract->setBasePrice(null);
        $contract->setBaseCurrency(null);
        $contract->setRateValue(null);
        $contract->setName(mb_substr($dto->getName() ?? $reference->getName() ?? '', 0, 255));
        if ($dto->getDescription() !== null) {
            $contract->setDescription(mb_substr($dto->getDescription(), 0, 255));
        }
        if ($dto->getActiveStartAt() !== null) {
            $contract->setActiveStartAt(new \DateTimeImmutable($dto->getActiveStartAt()));
        }
        if ($dto->getActiveEndAt() !== null) {
            $contract->setActiveEndAt(new \DateTimeImmutable($dto->getActiveEndAt()));
        }

        $this->em->flush();

        return $contract;
    }

    /**
     * @throws MyCurrentException
     */
    public function createByRate(CreateRateContractDto $dto): ContractBatchResult
    {
        $reference = $this->findReferenceOrFail((int) $dto->getReferencePackageId());

        $environment = $this->em->getRepository(Environment::class)->find($dto->getEnvironmentId());
        if ($environment === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Environment not found', 404);
        }

        $accounts = $this->targetAccountResolver->resolve($environment, $dto->getClients() ?? []);
        if ($accounts === []) {
            return new ContractBatchResult(0, 0, []);
        }

        $basePrice = (float) $dto->getBasePrice();
        $baseCurrency = strtoupper((string) $dto->getBaseCurrency());
        $currency = strtoupper((string) $dto->getCurrency());
        $rateValue = (float) $dto->getRateValue();

        $raw = round($basePrice * $rateValue, 2);
        $amount = $raw;
        $resultCurrency = $baseCurrency;
        $conversionRate = null;
        $conversionRateDate = null;
        $knowMore = null;

        if ($baseCurrency !== $currency) {
            $resolvedRate = $this->currencyConversionService->getRateDetails($baseCurrency, $currency);
            if ($resolvedRate !== null) {
                $amount = round($raw * $resolvedRate->rate, 2);
                $resultCurrency = $currency;
                $conversionRate = $resolvedRate->rate;
                $conversionRateDate = $resolvedRate->rateDate;
            } else {
                $knowMore = sprintf(
                    '[Pendiente conversión de moneda] No se pudo convertir %s a %s. '
                    . 'Importe mostrado en la moneda base sin convertir — revisar manualmente.',
                    $baseCurrency,
                    $currency,
                );
            }
        } else {
            $resultCurrency = $currency;
        }

        $created = 0;
        $updated = 0;
        $accountIds = [];

        foreach ($accounts as $account) {
            $isNew = $this->em->getRepository(CommunicationPricePackage::class)->findOneBy([
                'tenant' => $account,
                'referencePackage' => $reference,
                'isContract' => true,
            ], ['id' => 'DESC']) === null;

            $contract = $this->upsertContract($account, $reference);
            $contract->setContractMode('RATE');
            $contract->setBasePrice($basePrice);
            $contract->setBaseCurrency($baseCurrency);
            $contract->setRateValue($rateValue);
            $contract->setAmount($amount);
            $contract->setCurrency($resultCurrency);
            $contract->setConversionRate($conversionRate);
            $contract->setConversionRateDate($conversionRateDate);
            if ($knowMore !== null) {
                $contract->setKnowMore($knowMore);
            }
            $contract->setName(mb_substr($dto->getName() ?? $reference->getName() ?? '', 0, 255));
            if ($dto->getDescription() !== null) {
                $contract->setDescription(mb_substr($dto->getDescription(), 0, 255));
            }
            if ($dto->getActiveStartAt() !== null) {
                $contract->setActiveStartAt(new \DateTimeImmutable($dto->getActiveStartAt()));
            }
            if ($dto->getActiveEndAt() !== null) {
                $contract->setActiveEndAt(new \DateTimeImmutable($dto->getActiveEndAt()));
            }

            $isNew ? $created++ : $updated++;
            $accountIds[] = $account->getId();
        }

        $this->em->flush();

        return new ContractBatchResult($created, $updated, $accountIds);
    }

    /**
     * @throws MyCurrentException
     */
    private function findReferenceOrFail(int $referencePackageId): CommunicationClientPackage
    {
        $reference = $this->em->getRepository(CommunicationClientPackage::class)->find($referencePackageId);
        if ($reference === null) {
            throw new MyCurrentException('REFERENCE_PACKAGE_NOT_FOUND', 'Reference package not found', 404);
        }
        if ($reference->getTenant() !== null) {
            throw new MyCurrentException(
                'REFERENCE_PACKAGE_INVALID',
                'El paquete indicado no es una referencia (ya pertenece a un cliente)',
                422,
            );
        }
        if ($reference->getProduct() === null) {
            throw new MyCurrentException(
                'REFERENCE_PACKAGE_WITHOUT_PRODUCT',
                'El paquete referencia no tiene producto asociado, no se le puede crear un contrato',
                422,
            );
        }

        return $reference;
    }

    /**
     * Idempotente por (tenant, referencePackage): reutiliza el contrato
     * existente (el más reciente si hubiera más de uno) en vez de duplicar
     * — mismo criterio que CommunicationPromotionService.
     */
    private function upsertContract(Account $tenant, CommunicationClientPackage $reference): CommunicationPricePackage
    {
        $existing = $this->em->getRepository(CommunicationPricePackage::class)->findOneBy([
            'tenant' => $tenant,
            'referencePackage' => $reference,
            'isContract' => true,
        ], ['id' => 'DESC']);

        if ($existing !== null) {
            return $existing;
        }

        $contract = (new CommunicationPricePackage())
            ->setTenant($tenant)
            ->setReferencePackage($reference)
            ->setProduct($reference->getProduct())
            ->setIsContract(true)
            // price/priceCurrency son el snapshot de costo mayorista que
            // usa ProviderCatalogRefreshService — un contrato manual no lo
            // conoce, se deja en 0/currency del contrato para no romper el
            // NOT NULL de la columna (nunca se lee en un contrato real, ver
            // PackageSalePriceResolver: el costo mayorista solo importa en
            // la rama PRODUCT, sin contrato).
            ->setPrice(0.0)
            ->setPriceCurrency('USD');

        $this->em->persist($contract);

        return $contract;
    }
}
