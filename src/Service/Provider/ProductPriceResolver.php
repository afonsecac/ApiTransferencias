<?php

namespace App\Service\Provider;

use App\Entity\CommunicationProduct;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * El monto/moneda que se le cobra al cliente se basa en el COSTO mayorista
 * del proveedor (CommunicationProduct::price/priceCurrency) convertido a la
 * moneda del cliente — nunca en destinationAmount/destinationUnit (lo que
 * recibe el destinatario en Cuba, p.ej. CUP: puramente descriptivo, jamás
 * se cobra ni se convierte).
 *
 * Compartido por ClientCatalogImportService (alta de un cliente en un
 * proveedor nuevo) y ProviderCatalogRefreshService (refresco periódico) —
 * misma lógica de conversión/fallback/trazabilidad en un solo lugar.
 */
class ProductPriceResolver
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    ) {
    }

    public function resolve(CommunicationProduct $product, ?string $clientCurrency, ?int $accountIdForLogging = null): ResolvedProductPrice
    {
        $wholesalePrice = $product->getPrice() ?? 0.0;
        $wholesaleCurrency = $product->getPriceCurrency();

        if ($wholesaleCurrency === null || $clientCurrency === null || $wholesaleCurrency === $clientCurrency) {
            return new ResolvedProductPrice($wholesalePrice, $wholesaleCurrency ?? $clientCurrency, null, null, null);
        }

        $resolvedRate = $this->currencyConversionService->getRateDetails($wholesaleCurrency, $clientCurrency);
        if ($resolvedRate !== null) {
            $converted = round($wholesalePrice * $resolvedRate->rate, 2);

            return new ResolvedProductPrice($converted, $clientCurrency, $resolvedRate->rate, $resolvedRate->rateDate, null);
        }

        $this->providerLogger->warning('Resolución de precio: falló la conversión de moneda, se usa el costo mayorista sin convertir.', [
            'productId' => $product->getId(),
            'wholesaleCurrency' => $wholesaleCurrency,
            'clientCurrency' => $clientCurrency,
            'accountId' => $accountIdForLogging,
        ]);

        $note = sprintf(
            '[Pendiente conversión de moneda] No se pudo convertir %s a %s (servicio de cambio inalcanzable). '
            . 'Precio mostrado en la moneda original del proveedor — revisar manualmente.',
            $wholesaleCurrency,
            $clientCurrency,
        );

        return new ResolvedProductPrice($wholesalePrice, $wholesaleCurrency, null, null, $note);
    }
}
