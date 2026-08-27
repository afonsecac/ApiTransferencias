<?php

namespace App\Service\Pricing;

use App\Entity\CommunicationPackage;
use App\Enums\BenefitOperationEnum;
use App\Repository\CommunicationPackageRepository;

/**
 * Calcula EN VIVO (nunca persistido, ver los State Providers que lo llaman)
 * amount.base/promotion_bonus/totales de cada beneficio de un
 * CommunicationPackage que traiga `operation` (MULTIPLY/ADD/SET) —
 * BenefitOperationEnum, campo declarado en CreatePromotionV2Dto::$benefits
 * / CreateCommunicationPackageBatchDto::$benefits.
 *
 * Deliberadamente NO se resuelve al crear el paquete (Fase original de este
 * feature lo hacía así y se descartó): un "sextuplica" o "bonifica +20GB"
 * debe reflejar SIEMPRE el paquete regular equivalente vigente en el
 * momento en que se sirve el catálogo, no una foto fija tomada al crear la
 * promoción — si el paquete regular cambia su beneficio después, la
 * promoción activa debe reflejarlo en la siguiente petición, sin
 * necesidad de tocarla.
 *
 * Sin `operation`, un beneficio se devuelve tal cual — comportamiento
 * previo a este feature, intacto.
 */
class BenefitOperationResolver
{
    public function __construct(
        private readonly CommunicationPackageRepository $packageRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resolve(CommunicationPackage $package): array
    {
        $benefits = $package->getBenefits();
        $regularPackage = null;
        $regularPackageResolved = false;

        foreach ($benefits as &$benefit) {
            if (!is_array($benefit)) {
                continue;
            }
            $operation = BenefitOperationEnum::tryFrom((string) ($benefit['operation'] ?? ''));
            if ($operation === null || !is_numeric($benefit['value'] ?? null)) {
                continue;
            }

            if (($benefit['type'] ?? null) === 'CREDITS' && ($benefit['unit_type'] ?? null) === 'CURRENCY') {
                $baseline = (float) $package->getDestinationAmount();
            } else {
                // El paquete regular se resuelve una sola vez por llamada
                // (todos los beneficios de un mismo paquete comparten
                // tupla monto/moneda), no una vez por beneficio.
                if (!$regularPackageResolved) {
                    $regularPackage = $this->packageRepository->findByDestination(
                        (float) $package->getDestinationAmount(),
                        (string) $package->getDestinationCurrency(),
                    );
                    $regularPackageResolved = true;
                }
                $baseline = $this->findMatchingBenefitBase($regularPackage, $benefit);
            }

            $value = (float) $benefit['value'];
            [$base, $promotionBonus] = match ($operation) {
                BenefitOperationEnum::MULTIPLY => [$baseline, $baseline * ($value - 1)],
                BenefitOperationEnum::ADD => [$baseline, $value],
                BenefitOperationEnum::SET => [$value, 0.0],
            };

            $isCurrency = ($benefit['unit_type'] ?? null) === 'CURRENCY';
            $totalExcludingTax = $base + $promotionBonus;

            $benefit['amount'] = [
                'base' => self::normalizeNumber($base),
                'promotion_bonus' => self::normalizeNumber($promotionBonus),
                'total_excluding_tax' => self::normalizeNumber($totalExcludingTax),
                'total_including_tax' => self::normalizeNumber($totalExcludingTax),
            ];

            // El texto debe reflejar el resultado de la operación (ej.
            // "sextuplica" 800 CUP → "4800 CUP"), no el destinationAmount
            // original que dejó fijo applyCreditBenefitDefaults() al crear.
            if (($benefit['type'] ?? null) === 'CREDITS' && $isCurrency) {
                $benefit['additional_information'] = sprintf(
                    '%s %s',
                    self::formatAmount($totalExcludingTax),
                    (string) ($benefit['unit'] ?? ''),
                );
            }
        }
        unset($benefit);

        return $benefits;
    }

    /**
     * "1250" para montos enteros, "275.5" para fraccionarios — sin ceros de
     * relleno (igual que CommunicationPackageAdminService::formatAmount()).
     */
    private static function formatAmount(float $amount): string
    {
        return $amount == floor($amount)
            ? (string) (int) $amount
            : rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    /**
     * @param array<string, mixed> $benefit
     */
    private function findMatchingBenefitBase(?CommunicationPackage $regularPackage, array $benefit): float
    {
        if ($regularPackage === null) {
            return 0.0;
        }

        foreach ($regularPackage->getBenefits() as $regularBenefit) {
            if (($regularBenefit['type'] ?? null) === ($benefit['type'] ?? null)
                && ($regularBenefit['unit'] ?? null) === ($benefit['unit'] ?? null)
            ) {
                return (float) ($regularBenefit['amount']['base'] ?? 0);
            }
        }

        return 0.0;
    }

    private static function normalizeNumber(float $n): int|float
    {
        $rounded = round($n, 2);

        return $rounded == floor($rounded) ? (int) $rounded : $rounded;
    }
}
