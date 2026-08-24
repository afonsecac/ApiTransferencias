<?php

namespace App\Service\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
use App\Service\Catalog\CatalogVersionResolver;
use App\Service\Pricing\PackageMaterializationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Al routear un cliente a un proveedor nuevo (≠ ETECSA), este servicio
 * importa su catálogo automáticamente hasta dejarlo comprable de punta a
 * punta: sincroniza CommunicationProduct (ver CommunicationCatalogSyncService),
 * asegura un paquete REFERENCIA (CommunicationClientPackage con tenant
 * NULL) por cada producto habilitado, y materializa una copia por cada
 * cuenta activa del cliente en ese entorno (PackageMaterializationService)
 * — la asignación que de verdad habilita la compra vía la API de ventas
 * (ver CommunicationClientPackageRepository::getPackageById(), que es lo
 * que consulta CommunicationSaleService::processRecharge()/executeSale()).
 * Decisión explícita del usuario (2026-08-03): antes esto se dejaba como
 * paso manual del dashboard, pero para un proveedor ≠ ETECSA no tiene
 * sentido — routear y no poder vender nada hasta un segundo paso manual.
 * ETECSA sigue con su flujo propio (ya establecido, anterior a este
 * servicio) — no se toca.
 *
 * Precio: desde el rediseño de precios/paquetes, este servicio YA NO crea
 * ningún CommunicationPricePackage (contrato) — sin contrato,
 * PackageSalePriceResolver resuelve el precio contra el CommunicationProduct
 * vivo en el momento de listar/vender. Esto elimina de raíz el bug de
 * precio rancio que tenía la versión anterior (el refresco periódico de
 * catálogo solo actualizaba el CommunicationPricePackage, nunca el
 * ClientPackage materializado): ahora no hay nada que propagar, el
 * resolver siempre lee el costo vigente.
 *
 * Best-effort: un fallo al sincronizar (p.ej. proveedor inalcanzable) se
 * loguea y no impide que ProviderRoutingAdminService::create()/update()
 * complete la creación/actualización del routing en sí.
 *
 * Fase 3 de la deprecación de V1: la sincronización de `CommunicationProduct`
 * (`catalogSyncService->syncProducts()`) sigue corriendo SIEMPRE — la
 * necesitan tanto V1 como V2 (MAX+margen, coverage/bindings). Lo que
 * cambió es la materialización de `CommunicationClientPackage` (referencia +
 * copia por cuenta): solo se hace para cuentas que SIGUEN siendo V1
 * (`CatalogVersionResolver::isV2()`) — una cuenta V2 nunca lee esas filas
 * (su catálogo es `CommunicationPackage`, resuelto por
 * `PackageCatalogResolver`), así que crearlas era puro desperdicio.
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
        private readonly PackageMaterializationService $materializationService,
        private readonly ProductSaleTypeMatcher $saleTypeMatcher,
        private readonly CatalogVersionResolver $catalogVersion,
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

            // Fase 3 de la deprecación de V1: materializar un
            // CommunicationClientPackage (referencia + copia por cuenta) es
            // puro desperdicio para una cuenta ya V2 — su catálogo se
            // resuelve contra CommunicationPackage (PackageCatalogResolver),
            // nunca lee estas filas. Antes de este fix, routear un cliente ya
            // V2 a un proveedor nuevo seguía creando filas V1 sin que nada
            // las usara jamás — era el "grifo abierto" real encontrado en la
            // investigación de esta deprecación (confirmado contra staging Y
            // producción, no solo dev).
            $v1Accounts = array_values(array_filter(
                $accounts,
                fn (Account $account) => !$this->catalogVersion->isV2($account),
            ));
            if ($v1Accounts === []) {
                continue;
            }

            foreach ($products as $product) {
                if (!$this->saleTypeMatcher->matches($product, $routing->getSaleType())) {
                    continue;
                }

                [$reference, $referenceCreated] = $this->findOrCreateReferencePackage($product, $environment);
                if ($referenceCreated) {
                    $touched = true;
                }

                foreach ($v1Accounts as $account) {
                    if ($this->createClientPackageIfMissing($reference, $account)) {
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
     * @return array{0: CommunicationClientPackage, 1: bool} la referencia
     *   (existente o recién creada) y si se acaba de crear — un solo
     *   paquete referencia por producto (índice único parcial
     *   uniq_ccp_reference_product), del que se materializa una copia por
     *   cuenta más abajo.
     */
    private function findOrCreateReferencePackage(CommunicationProduct $product, Environment $environment): array
    {
        $existing = $this->em->getRepository(CommunicationClientPackage::class)->findOneBy([
            'product' => $product,
            'tenant' => null,
        ]);
        if ($existing !== null) {
            return [$existing, false];
        }

        $name = $product->getDescription() ?: ('Producto ' . $product->getExternalRef());

        $reference = new CommunicationClientPackage();
        $reference->setProduct($product);
        $reference->setEnvironment($environment);
        $reference->setName($name);
        $reference->setDescription($name);
        $reference->setActiveStartAt(new \DateTimeImmutable('now'));
        // Sin esto queda null y getPackageById() (que exige activeEndAt >
        // ahora) nunca encuentra los paquetes materializados de esta
        // referencia — mismo error que ya cometí a mano el 2026-08-02 al
        // crear uno de prueba sin este campo.
        $reference->setActiveEndAt(new \DateTimeImmutable(self::INDEFINITE_VALIDITY_END));
        $reference->setBenefits($product->getBenefits());
        $reference->setTags($this->deriveTags($product));
        $reference->setService($product->getService());
        $reference->setDestination([
            'amount' => $product->getDestinationAmount(),
            'unit' => $product->getDestinationUnit(),
            'unit_type' => 'CURRENCY',
        ]);
        $reference->setValidity(['quantity' => null, 'unit' => null]);

        $this->em->persist($reference);

        return [$reference, true];
    }

    /**
     * La asignación que de verdad habilita la compra: sin esta fila,
     * CommunicationClientPackageRepository::getPackageById() nunca encuentra
     * el paquete y la API de ventas responde COM003 "The package don't
     * exist" aunque el producto/referencia sí existan (confirmado en vivo
     * el 2026-08-02 contra el sandbox de DTOne). Delegado en
     * PackageMaterializationService — mismo clonado que usa la auto-copia
     * perezosa de CommunicationClientPackageProvider.
     */
    private function createClientPackageIfMissing(CommunicationClientPackage $reference, Account $account): bool
    {
        $existing = $this->em->getRepository(CommunicationClientPackage::class)->findOneBy([
            'tenant' => $account,
            'referencePackage' => $reference,
        ]);
        if ($existing !== null) {
            return false;
        }

        $this->materializationService->materializeForTenant($reference, $account);

        return true;
    }

    /**
     * Deriva tags a partir del `subservice` crudo del proveedor, más un tag
     * `UNLIMITED` adicional cuando el primer benefit es de datos ilimitados
     * (`type=DATA` con `amount.base` negativo — convención de DTOne para
     * "sin límite", ver Nauta PLUS). Necesario porque Nauta WIFI Recharge y
     * Nauta PLUS comparten el mismo `subservice=Internet` pero son productos
     * distintos (recarga de saldo vs. plan de datos ilimitado por días) —
     * sin este tag extra quedarían indistinguibles en el catálogo.
     *
     * @return list<string>
     */
    private function deriveTags(?CommunicationProduct $product): array
    {
        $allowedTags = ['AIRTIME', 'BUNDLE', 'DATA', 'SMS', 'INTERNET', 'LANDLINE'];
        $subservice = $product?->getService()['subservice']['name'] ?? null;

        $tags = [];
        if ($subservice !== null) {
            $normalized = strtoupper($subservice);
            if (in_array($normalized, $allowedTags, true)) {
                $tags[] = $normalized;
            }
        }

        if ($this->hasUnlimitedDataBenefit($product)) {
            $tags[] = 'UNLIMITED';
        }

        return $tags;
    }

    private function hasUnlimitedDataBenefit(?CommunicationProduct $product): bool
    {
        $firstBenefit = ($product?->getBenefits() ?? [])[0] ?? null;
        if (!is_array($firstBenefit)) {
            return false;
        }

        return ($firstBenefit['type'] ?? null) === 'DATA'
            && (float) ($firstBenefit['amount']['base'] ?? 0) < 0;
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
