<?php

namespace App\Service\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Enums\CommunicationProviderEnum;
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
                if (!$this->saleTypeMatcher->matches($product, $routing->getSaleType())) {
                    continue;
                }

                [$reference, $referenceCreated] = $this->findOrCreateReferencePackage($product, $environment);
                if ($referenceCreated) {
                    $touched = true;
                }

                foreach ($accounts as $account) {
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
