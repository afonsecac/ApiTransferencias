<?php

namespace App\Service\Provider;

use App\Entity\CurrencyExchangeRate;
use App\Repository\CurrencyExchangeRateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Conversión de moneda contra el histórico ya sincronizado en
 * currency_exchange_rate (ver CurrencyExchangeRateSyncService), NUNCA
 * contra Frankfurter en vivo: una importación de catálogo no debe depender
 * de que el servicio de cambio responda justo en ese instante. Si no hay
 * ninguna tasa guardada para el par pedido (el comando programado nunca
 * corrió, o corrió pero ese par no está entre las monedas de Frankfurter),
 * devuelve null — el llamador decide el fallback (ver
 * ClientCatalogImportService: usa el costo mayorista sin convertir).
 *
 * Solo para monedas "normales" (USD, EUR, GBP...) — nunca para CUP, que ni
 * siquiera está en el catálogo de Frankfurter. Esta conversión es para el
 * COSTO mayorista del proveedor frente a la moneda del cliente, no para el
 * monto que recibe el destinatario en Cuba.
 *
 * Todas las tasas guardadas tienen como base EUR (ver
 * CurrencyExchangeRateSyncService::BASE_CURRENCY); un par cruzado (p.ej.
 * USD→GBP) se deriva combinando dos tasas EUR→X ya guardadas.
 */
class CurrencyConversionService
{
    /** @var array<string, ResolvedExchangeRate|null> */
    private array $rateCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    ) {
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        if (strtoupper($fromCurrency) === strtoupper($toCurrency)) {
            return $amount;
        }

        $rate = $this->getRate($fromCurrency, $toCurrency);

        return $rate !== null ? round($amount * $rate, 2) : null;
    }

    public function getRate(string $fromCurrency, string $toCurrency): ?float
    {
        return $this->getRateDetails($fromCurrency, $toCurrency)?->rate;
    }

    /**
     * Igual que getRate() pero además expone la fecha de referencia de la
     * tasa aplicada — necesario para dejar trazabilidad de qué tasa se usó
     * en un CommunicationPricePackage concreto (ver ClientCatalogImportService).
     */
    public function getRateDetails(string $fromCurrency, string $toCurrency): ?ResolvedExchangeRate
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return new ResolvedExchangeRate(1.0, null);
        }

        $cacheKey = $from . '_' . $to;
        if (array_key_exists($cacheKey, $this->rateCache)) {
            return $this->rateCache[$cacheKey];
        }

        $resolved = $this->resolveRate($from, $to);
        $this->rateCache[$cacheKey] = $resolved;

        return $resolved;
    }

    private function resolveRate(string $from, string $to): ?ResolvedExchangeRate
    {
        $base = CurrencyExchangeRateSyncService::BASE_CURRENCY;

        if ($from === $base) {
            $row = $this->latestRow($base, $to);

            return $row !== null ? new ResolvedExchangeRate($row->getRate(), $row->getRateDate()) : null;
        }

        if ($to === $base) {
            $row = $this->latestRow($base, $from);
            if ($row === null || $row->getRate() <= 0.0) {
                return null;
            }

            return new ResolvedExchangeRate(1 / $row->getRate(), $row->getRateDate());
        }

        $fromRow = $this->latestRow($base, $from);
        $toRow = $this->latestRow($base, $to);

        if ($fromRow === null || $toRow === null || $fromRow->getRate() <= 0.0) {
            $this->providerLogger->warning('Conversión de moneda: falta tasa guardada para el par pedido.', [
                'from' => $from,
                'to' => $to,
                'fromRateFound' => $fromRow !== null,
                'toRateFound' => $toRow !== null,
            ]);

            return null;
        }

        // Ambas tasas vienen del mismo lote de sincronización en el uso
        // normal; se reporta la más reciente de las dos por si no lo fueran.
        $rateDate = $toRow->getRateDate();
        if ($fromRow->getRateDate() !== null && ($rateDate === null || $fromRow->getRateDate() > $rateDate)) {
            $rateDate = $fromRow->getRateDate();
        }

        return new ResolvedExchangeRate($toRow->getRate() / $fromRow->getRate(), $rateDate);
    }

    private function latestRow(string $base, string $currency): ?CurrencyExchangeRate
    {
        /** @var CurrencyExchangeRateRepository $repo */
        $repo = $this->em->getRepository(CurrencyExchangeRate::class);

        return $repo->findLatest($base, $currency);
    }
}
