<?php

namespace App\Service\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Al routear un cliente a un proveedor nuevo (≠ ETECSA), este servicio
 * importa su catálogo automáticamente hasta dejarlo comprable de punta a
 * punta: sincroniza CommunicationProduct (ver CommunicationCatalogSyncService),
 * crea un CommunicationPricePackage por cada (producto × cuenta activa del
 * cliente en ese entorno) que todavía no exista, y a partir de esa fila crea
 * también el CommunicationClientPackage correspondiente — la asignación que
 * de verdad habilita la compra vía la API de ventas (ver
 * CommunicationClientPackageRepository::getPackageById(), que es lo que
 * consulta CommunicationSaleService::processRecharge()/executeSale()).
 * Decisión explícita del usuario (2026-08-03): antes esto se dejaba como
 * paso manual del dashboard, pero para un proveedor ≠ ETECSA no tiene
 * sentido — routear y no poder vender nada hasta un segundo paso manual.
 * ETECSA sigue con su flujo propio (ya establecido, anterior a este
 * servicio) — no se toca.
 *
 * Precio y conversión: ver ProductPriceResolver (compartido con el refresco
 * periódico, ProviderCatalogRefreshService). Cada CommunicationPricePackage
 * creado aquí queda marcado autoManaged=true y con conversionRate/
 * conversionRateDate cuando hubo conversión — trazabilidad de qué tasa se
 * usó, y señal para que el refresco periódico sepa que puede tocarla (nunca
 * toca una fila creada o editada a mano por un admin).
 *
 * Best-effort: un fallo al sincronizar (p.ej. proveedor inalcanzable) se
 * loguea y no impide que ProviderRoutingAdminService::create()/update()
 * complete la creación/actualización del routing en sí.
 */
class ClientCatalogImportService
{
    /**
     * Fecha de "sin vencimiento real" — mismo convenio ya usado en todo el
     * sistema para paquetes de cliente que no tienen una fecha de fin de
     * verdad (ver CommunicationClientPackage existentes de ETECSA).
     */
    private const INDEFINITE_VALIDITY_END = '2030-01-02 04:59:59';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommunicationCatalogSyncService $catalogSyncService,
        private readonly ProductPriceResolver $priceResolver,
        #[Autowire('@monolog.logger.provider')]
        private readonly LoggerInterface $providerLogger,
    ) {
    }

    public function importForRouting(ClientProviderRouting $routing): void
    {
        $providerCode = CommunicationProviderEnum::tryFrom($routing->getProvider() ?? '');
        if ($providerCode === null || $providerCode === CommunicationProviderEnum::ETECSA) {
            return;
        }

        $client = $routing->getClient();
        if ($client === null) {
            return;
        }

        $environments = $this->resolveEnvironments($client, $routing->getEnvironment());
        $touched = false;

        foreach ($environments as $environment) {
            try {
                $this->catalogSyncService->syncProducts($providerCode, $environment);
            } catch (\Throwable $e) {
                $this->providerLogger->error('Import de catálogo: falló el sync de productos, se omite este entorno.', [
                    'provider' => $providerCode->value,
                    'environmentId' => $environment->getId(),
                    'clientId' => $client->getId(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $products = $this->em->getRepository(CommunicationProduct::class)->findBy([
                'environment' => $environment,
                'provider' => $providerCode->value,
                'enabled' => true,
            ]);

            if ($products === []) {
                continue;
            }

            $accounts = $this->em->getRepository(Account::class)->findBy([
                'client' => $client,
                'environment' => $environment,
                'isActive' => true,
            ]);

            foreach ($products as $product) {
                if (!$this->matchesSaleType($product, $routing->getSaleType())) {
                    continue;
                }

                foreach ($accounts as $account) {
                    [$pricePackage, $pricePackageCreated] = $this->findOrCreatePricePackage($product, $account, $client);
                    $clientPackageCreated = $this->createClientPackageIfMissing($pricePackage, $account);
                    if ($pricePackageCreated || $clientPackageCreated) {
                        $touched = true;
                    }
                }
            }
        }

        if ($touched) {
            $this->em->flush();
        }
    }

    /**
     * Un enrutado con `saleType` fijo ('recharge' o 'sale') solo debe traer
     * los paquetes de precio que de verdad se pueden vender bajo ese modo.
     * `saleType === null` significa que el enrutado aplica a ambos modos,
     * así que no se filtra nada.
     *
     * "Elegible para recarga" exige DOS condiciones a la vez:
     *  - mismo criterio que CommunicationSaleService::assertRechargeableProduct():
     *    todo lo que sea PIN_PURCHASE se vende siempre como paquete, nunca
     *    como recarga.
     *  - CommunicationProduct::isMobileOrInternetService(): "recharge" es
     *    solo telefonía móvil o Internet (decisión de negocio) — todo lo
     *    demás (gift cards, comida, SIM/equipos físicos...) es 'sale' aunque
     *    el proveedor lo entregue como si fuera una recarga directa.
     */
    private function matchesSaleType(CommunicationProduct $product, ?string $saleType): bool
    {
        if ($saleType === null) {
            return true;
        }

        $isPinPurchase = str_contains($product->getPackageType() ?? '', 'PIN_PURCHASE');
        $rechargeEligible = !$isPinPurchase && $product->isMobileOrInternetService();

        return $saleType === 'sale' ? !$rechargeEligible : $rechargeEligible;
    }

    /**
     * @return array{0: CommunicationPricePackage, 1: bool} la fila (existente
     *   o recién creada) y si se acaba de crear — el paso siguiente
     *   (createClientPackageIfMissing) necesita la fila en ambos casos, para
     *   poder rellenar el hueco de asignación aunque el precio ya existiera
     *   de antes (p.ej. de una importación previa a este cambio).
     */
    private function findOrCreatePricePackage(CommunicationProduct $product, Account $account, Client $client): array
    {
        $existing = $this->em->getRepository(CommunicationPricePackage::class)->findOneBy([
            'product' => $product,
            'tenant' => $account,
        ]);
        if ($existing !== null) {
            return [$existing, false];
        }

        $pricePackage = new CommunicationPricePackage();
        $pricePackage->setProduct($product);
        $pricePackage->setTenant($account);
        $pricePackage->setEnvironment($account->getEnvironment());
        $name = $product->getDescription() ?: ('Producto ' . $product->getExternalRef());
        $pricePackage->setName($name);
        $pricePackage->setDescription($product->getDescription());
        $pricePackage->setPrice($product->getPrice() ?? 0.0);
        if ($product->getPriceCurrency() !== null) {
            $pricePackage->setPriceCurrency($product->getPriceCurrency());
        }
        $pricePackage->markAutoManaged();

        $resolved = $this->priceResolver->resolve($product, $client->getCurrency(), $account->getId());
        $pricePackage->setAmount($resolved->amount);
        if ($resolved->currency !== null) {
            $pricePackage->setCurrency($resolved->currency);
        }
        $pricePackage->setConversionRate($resolved->conversionRate);
        $pricePackage->setConversionRateDate($resolved->conversionRateDate);
        if ($resolved->pendingNote !== null) {
            $pricePackage->setKnowMore($resolved->pendingNote);
        }

        $this->em->persist($pricePackage);

        return [$pricePackage, true];
    }

    /**
     * La asignación que de verdad habilita la compra: sin esta fila,
     * CommunicationClientPackageRepository::getPackageById() nunca encuentra
     * el paquete y la API de ventas responde COM003 "The package don't
     * exist" aunque el CommunicationPricePackage sí exista (confirmado en
     * vivo el 2026-08-02 contra el sandbox de DTOne).
     */
    private function createClientPackageIfMissing(CommunicationPricePackage $pricePackage, Account $account): bool
    {
        $existing = $this->em->getRepository(CommunicationClientPackage::class)->findOneBy([
            'tenant' => $account,
            'priceClientPackage' => $pricePackage,
        ]);
        if ($existing !== null) {
            return false;
        }

        $product = $pricePackage->getProduct();
        $name = $product?->getDescription() ?: $pricePackage->getName();

        $clientPackage = new CommunicationClientPackage();
        $clientPackage->setTenant($account);
        $clientPackage->setPriceClientPackage($pricePackage);
        $clientPackage->setEnvironment($pricePackage->getEnvironment());
        $clientPackage->setName($name ?? '');
        $clientPackage->setDescription($name ?? '');
        $clientPackage->setAmount($pricePackage->getAmount() ?? 0.0);
        $clientPackage->setCurrency($pricePackage->getCurrency() ?? '');
        $clientPackage->setActiveStartAt(new \DateTimeImmutable('now'));
        // Sin esto queda null y getPackageById() (que exige activeEndAt >
        // ahora) nunca lo encuentra — mismo error que ya cometí a mano el
        // 2026-08-02 al crear uno de prueba sin este campo.
        $clientPackage->setActiveEndAt(new \DateTimeImmutable(self::INDEFINITE_VALIDITY_END));
        $clientPackage->setBenefits($product?->getBenefits() ?? []);
        $clientPackage->setTags($this->deriveTags($product));
        $clientPackage->setService($product?->getService() ?? []);
        $clientPackage->setDestination([
            'amount' => $product?->getDestinationAmount(),
            'unit' => $product?->getDestinationUnit(),
            'unit_type' => 'CURRENCY',
        ]);
        $clientPackage->setValidity(['quantity' => null, 'unit' => null]);

        $this->em->persist($clientPackage);

        return true;
    }

    /**
     * @return list<string>
     */
    private function deriveTags(?CommunicationProduct $product): array
    {
        $allowedTags = ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET'];
        $subservice = $product?->getService()['subservice']['name'] ?? null;
        if ($subservice === null) {
            return [];
        }

        $normalized = strtoupper($subservice);

        return in_array($normalized, $allowedTags, true) ? [$normalized] : [];
    }

    /**
     * @return list<Environment>
     */
    private function resolveEnvironments(Client $client, ?Environment $routingEnvironment): array
    {
        if ($routingEnvironment !== null) {
            return [$routingEnvironment];
        }

        $accounts = $this->em->getRepository(Account::class)->findBy([
            'client' => $client,
            'isActive' => true,
        ]);

        $environments = [];
        foreach ($accounts as $account) {
            $environment = $account->getEnvironment();
            if ($environment !== null) {
                $environments[$environment->getId()] = $environment;
            }
        }

        return array_values($environments);
    }
}
