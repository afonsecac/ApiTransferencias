<?php

namespace App\Service\Pricing;

use App\DTO\CreateCommunicationPackageBatchDto;
use App\DTO\CreateCommunicationPackageDto;
use App\DTO\UpdateCommunicationPackageDto;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Exception\MyCurrentException;
use App\Repository\CommunicationProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * CRUD administrativo de CommunicationPackage (catálogo agnóstico de
 * proveedor, V2) — dashboard, no venta. La resolución de precio/visibilidad
 * para un cliente vive en PackageCatalogResolver, no aquí.
 */
class CommunicationPackageAdminService
{
    /**
     * Tope duro de paquetes generables en una sola llamada a createBatch()
     * — protege contra un rango/salto mal calculado (ej. step demasiado
     * chico) que intente insertar miles de filas de un golpe.
     */
    private const MAX_BATCH_SIZE = 200;

    /**
     * Comisión aplicada sobre total_excluding_tax para obtener
     * total_including_tax en los beneficios CREDITS/CURRENCY. Sin lógica de
     * fee todavía — se deja en 0 hasta que se defina.
     */
    private const CURRENCY_FEE_PERCENT = 0.0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationProductRepository $productRepository,
    ) {
    }

    public function create(CreateCommunicationPackageDto $dto): CommunicationPackage
    {
        $package = new CommunicationPackage();
        $package->setName((string) $dto->getName());
        $package->setDescription((string) $dto->getDescription());
        $package->setKnowMore($dto->getKnowMore());
        $package->setDestinationAmount((float) $dto->getDestinationAmount());
        $package->setDestinationCurrency(strtoupper((string) $dto->getDestinationCurrency()));
        if ($dto->getIsActive() !== null) {
            $package->setIsActive($dto->getIsActive());
        }
        if ($dto->getActiveStartAt() !== null) {
            $package->setActiveStartAt(self::parseUtc($dto->getActiveStartAt()));
        }
        if ($dto->getActiveEndAt() !== null) {
            $package->setActiveEndAt(self::parseUtc($dto->getActiveEndAt()));
        }
        if ($dto->getDisplayOrder() !== null) {
            $package->setDisplayOrder($dto->getDisplayOrder());
        }
        $package->setBenefits($dto->getBenefits() ?? []);
        $package->setTags($dto->getTags() ?? []);
        $package->setService($dto->getService() ?? []);
        $package->setValidity($dto->getValidity());
        $this->applyCreditBenefitDefaults($package);

        $this->em->persist($package);
        $this->em->flush();

        return $package;
    }

    /**
     * Genera un CommunicationPackage por cada monto en [fromAmount,
     * toAmount] saltando de `step` en `step` (ambos extremos incluidos),
     * sustituyendo "{monto}" en nameTemplate/descriptionTemplate por el
     * monto de cada uno. Los montos se calculan por índice
     * (from + i*step), no acumulando `+=`, para no arrastrar error de
     * coma flotante en rangos largos.
     *
     * @return list<CommunicationPackage>
     *
     * @throws MyCurrentException si el rango genera más de MAX_BATCH_SIZE paquetes
     */
    public function createBatch(CreateCommunicationPackageBatchDto $dto): array
    {
        $from = (float) $dto->getFromAmount();
        $to = (float) $dto->getToAmount();
        $step = (float) $dto->getStep();

        $count = (int) floor(($to - $from) / $step + 1e-9) + 1;
        if ($count > self::MAX_BATCH_SIZE) {
            throw new MyCurrentException(
                'PACKAGE_BATCH_TOO_LARGE',
                sprintf('El rango genera %d paquetes; el máximo permitido es %d', $count, self::MAX_BATCH_SIZE),
                422,
            );
        }

        $currency = strtoupper((string) $dto->getDestinationCurrency());
        $nameTemplate = (string) $dto->getNameTemplate();
        $descriptionTemplate = (string) $dto->getDescriptionTemplate();

        $packages = [];
        for ($i = 0; $i < $count; $i++) {
            $amount = round($from + $i * $step, 2);

            $package = new CommunicationPackage();
            $package->setName($this->renderTemplate($nameTemplate, $amount));
            $package->setDescription($this->renderTemplate($descriptionTemplate, $amount));
            $package->setKnowMore($dto->getKnowMore());
            $package->setDestinationAmount($amount);
            $package->setDestinationCurrency($currency);
            if ($dto->getIsActive() !== null) {
                $package->setIsActive($dto->getIsActive());
            }
            if ($dto->getActiveStartAt() !== null) {
                $package->setActiveStartAt(self::parseUtc($dto->getActiveStartAt()));
            }
            if ($dto->getActiveEndAt() !== null) {
                $package->setActiveEndAt(self::parseUtc($dto->getActiveEndAt()));
            }
            if ($dto->getDisplayOrder() !== null) {
                $package->setDisplayOrder($dto->getDisplayOrder());
            }
            $package->setBenefits($dto->getBenefits() ?? []);
            $package->setTags($dto->getTags() ?? []);
            $package->setService($dto->getService() ?? []);
            $package->setValidity($dto->getValidity());
            $this->applyCreditBenefitDefaults($package);

            $this->em->persist($package);
            $packages[] = $package;
        }

        $this->em->flush();

        return $packages;
    }

    private function renderTemplate(string $template, float $amount): string
    {
        return str_replace('{monto}', self::formatAmount($amount), $template);
    }

    /**
     * "1250" para montos enteros, "275.5" para fraccionarios — sin ceros de
     * relleno. Usado tanto en nameTemplate/descriptionTemplate como en
     * additional_information de los beneficios CREDITS/CURRENCY.
     */
    private static function formatAmount(float $amount): string
    {
        return $amount == floor($amount)
            ? (string) (int) $amount
            : rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    /**
     * Deriva additional_information/amount.base/total_excluding_tax/
     * total_including_tax de los beneficios CREDITS+CURRENCY a partir de
     * destinationAmount/destinationCurrency del propio paquete — son la
     * representación textual/numérica de "cuánto se acredita al
     * destinatario", así que no se dejan a criterio manual del admin.
     * promotion_bonus SÍ se conserva tal cual venga (0 si no se especifica):
     * es el único componente realmente editable de amount hasta que exista
     * lógica de promociones. Se recalcula siempre — en create(), en cada
     * paquete de createBatch() y en update() — para que nunca quede
     * desincronizado de destinationAmount tras una edición.
     */
    private function applyCreditBenefitDefaults(CommunicationPackage $package): void
    {
        $amount = (float) $package->getDestinationAmount();
        $currency = (string) $package->getDestinationCurrency();
        $benefits = $package->getBenefits();

        foreach ($benefits as &$benefit) {
            if (($benefit['type'] ?? null) !== 'CREDITS' || ($benefit['unit_type'] ?? null) !== 'CURRENCY') {
                continue;
            }

            $benefit['additional_information'] = sprintf('%s %s', self::formatAmount($amount), $currency);

            $base = $amount;
            $promotionBonus = (float) ($benefit['amount']['promotion_bonus'] ?? 0);
            $totalExcludingTax = $base + $promotionBonus;
            $totalIncludingTax = $totalExcludingTax * (1 + self::CURRENCY_FEE_PERCENT / 100);

            $benefit['amount'] = [
                'base' => self::normalizeNumber($base),
                'promotion_bonus' => self::normalizeNumber($promotionBonus),
                'total_excluding_tax' => self::normalizeNumber($totalExcludingTax),
                'total_including_tax' => self::normalizeNumber($totalIncludingTax),
            ];
        }
        unset($benefit);

        $package->setBenefits($benefits);
    }

    private static function normalizeNumber(float $n): int|float
    {
        $rounded = round($n, 2);

        return $rounded == floor($rounded) ? (int) $rounded : $rounded;
    }

    /**
     * active_start_at/active_end_at son columnas `timestamp without time
     * zone`: Doctrine las escribe con `format()` tal cual (sin convertir) y
     * las relee asumiendo el timezone por defecto de PHP (UTC). Si no se
     * normaliza aquí, un ISO con offset (ej. "-04:00" de Cuba) se guarda con
     * esos dígitos literales y al releer se reinterpreta como UTC,
     * desplazando la hora mostrada en el frontend.
     */
    private static function parseUtc(string $iso): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('UTC'));
    }

    public function update(CommunicationPackage $package, UpdateCommunicationPackageDto $dto): CommunicationPackage
    {
        if ($dto->getName() !== null) {
            $package->setName($dto->getName());
        }
        if ($dto->getDescription() !== null) {
            $package->setDescription($dto->getDescription());
        }
        if ($dto->getKnowMore() !== null) {
            $package->setKnowMore($dto->getKnowMore());
        }
        if ($dto->getDestinationAmount() !== null) {
            $package->setDestinationAmount($dto->getDestinationAmount());
        }
        if ($dto->getDestinationCurrency() !== null) {
            $package->setDestinationCurrency(strtoupper($dto->getDestinationCurrency()));
        }
        if ($dto->getIsActive() !== null) {
            $package->setIsActive($dto->getIsActive());
        }
        if ($dto->getActiveStartAt() !== null) {
            $package->setActiveStartAt(self::parseUtc($dto->getActiveStartAt()));
        }
        if ($dto->getActiveEndAt() !== null) {
            $package->setActiveEndAt(self::parseUtc($dto->getActiveEndAt()));
        }
        if ($dto->getDisplayOrder() !== null) {
            $package->setDisplayOrder($dto->getDisplayOrder());
        }
        if ($dto->getBenefits() !== null) {
            $package->setBenefits($dto->getBenefits());
        }
        if ($dto->getTags() !== null) {
            $package->setTags($dto->getTags());
        }
        if ($dto->getService() !== null) {
            $package->setService($dto->getService());
        }
        if ($dto->getValidity() !== null) {
            $package->setValidity($dto->getValidity());
        }
        $this->applyCreditBenefitDefaults($package);

        $this->em->flush();

        return $package;
    }

    public function toggle(CommunicationPackage $package): void
    {
        $package->setIsActive(!$package->isActive());
        $this->em->flush();
    }

    /**
     * @throws MyCurrentException si tiene contratos asociados (la tabla
     *   puente communication_contract_package tiene FK ON DELETE CASCADE
     *   desde el lado paquete, así que SIN esta guardia el borrado
     *   desasociaría al paquete de sus contratos en silencio en vez de
     *   avisar — un contrato con 0 paquetes queda huérfano).
     */
    public function delete(CommunicationPackage $package): void
    {
        if (!$package->getContracts()->isEmpty()) {
            throw new MyCurrentException(
                'COMMUNICATION_PACKAGE_HAS_CONTRACTS',
                'No se puede eliminar: tiene contratos asociados',
                409,
            );
        }

        $this->em->remove($package);
        $this->em->flush();
    }

    /**
     * Qué proveedores cubren la tupla de destino de este paquete, y a qué
     * costo mayorista — evita publicar un paquete indespachable (ver
     * DashboardCommunicationPackagesController::coverage()).
     *
     * @return list<array{provider: string, productId: int, externalRef: string, description: ?string, wholesalePrice: float, priceCurrency: ?string}>
     */
    public function coverage(CommunicationPackage $package, ?Environment $environment): array
    {
        $products = $this->productRepository->findMatchingDestination(
            (float) $package->getDestinationAmount(),
            (string) $package->getDestinationCurrency(),
            $environment,
        );

        return array_map(static fn (CommunicationProduct $product) => [
            'provider' => $product->getProvider(),
            'productId' => $product->getId(),
            'externalRef' => $product->getExternalRef(),
            'description' => $product->getDescription(),
            'wholesalePrice' => $product->getPrice() ?? 0.0,
            'priceCurrency' => $product->getPriceCurrency(),
        ], $products);
    }
}
