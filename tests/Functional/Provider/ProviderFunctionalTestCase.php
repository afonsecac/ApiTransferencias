<?php

namespace App\Tests\Functional\Provider;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationContract;
use App\Entity\CommunicationPackage;
use App\Entity\CommunicationPackageProviderProduct;
use App\Entity\CommunicationPricePackage;
use App\Entity\CommunicationProduct;
use App\Entity\Environment;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Fixtures reales (persistidas y flusheadas contra Postgres, no mocks) para
 * los tests funcionales del enrutado multi-proveedor.
 */
abstract class ProviderFunctionalTestCase extends FunctionalTestCase
{
    protected function authenticateAs(Account $account): void
    {
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($account, 'main', $account->getRoles()));
    }

    /**
     * Usuario admin del dashboard (distinto de Account, que autentica a
     * clientes de la API móvil) — necesario para probar auditoría de
     * acciones MANUAL (ProviderAvailabilityService::setManual()).
     */
    protected function createAdminUser(): User
    {
        static $counter = 0;
        $counter++;

        $user = (new User())
            ->setEmail("admin-func-{$counter}@example.test")
            ->setPassword('irrelevant-hash')
            ->setFirstName('Admin')
            ->setLastName("Funcional {$counter}")
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setIsActive(true)
            ->setIsCheckValidation(true);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function authenticateAsAdmin(User $user): void
    {
        $tokenStorage = self::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    protected function createClient(bool $isActive = true): Client
    {
        static $counter = 0;
        $counter++;

        $client = (new Client())
            ->setCompanyName("Cliente Funcional {$counter}")
            ->setCompanyCountry('CUB')
            ->setCompanyEmail("cliente{$counter}@example.test")
            ->setCompanyPhoneNumber('55500000' . $counter)
            ->setCompanyIdentification("ID-{$counter}")
            ->setCompanyIdentificationType('NIT')
            ->setDiscountOfClient(0.0)
            ->setIsActive($isActive);

        $this->em->persist($client);
        $this->em->flush();

        return $client;
    }

    protected function createEnvironment(string $type = 'TEST'): Environment
    {
        static $counter = 0;
        $counter++;

        $environment = (new Environment())
            ->setType($type)
            ->setBasePath('https://etecsa.example.test')
            ->setProviderName("ETECSA-{$counter}")
            ->setClientSecret("secret-{$counter}")
            ->setClientId("client-{$counter}")
            ->setDiscount(0.0)
            ->setDiscountType('%')
            ->setIsActive(true);

        $this->em->persist($environment);
        $this->em->flush();

        return $environment;
    }

    protected function createAccount(Client $client, Environment $environment): Account
    {
        $account = (new Account())
            ->setAccessToken(Uuid::v4())
            ->setEnvironment($environment)
            ->setClient($client)
            ->setIsActive(true)
            ->setOrigin('functional-test')
            ->setEnvironmentName($environment->getType());

        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    /**
     * Producto + tarifa + paquete de cliente listo para venderse (activo,
     * con activeEndAt en el futuro, tal como exige
     * CommunicationClientPackageRepository::getPackageById()).
     */
    protected function createSellablePackage(
        Environment $environment,
        Account $account,
        string $provider,
        float $amount = 5.0,
        string $currency = 'USD',
    ): CommunicationClientPackage {
        static $counter = 0;
        $counter++;

        $product = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId(100000 + $counter)
            ->setPackageType('RECHARGE')
            ->setPrice($amount)
            ->setEnabled(true)
            ->setDescription("Producto funcional {$counter}")
            ->setProvider($provider)
            ->setExternalRef((string) (100000 + $counter));

        $pricePackage = (new CommunicationPricePackage())
            ->setProduct($product)
            ->setPrice($amount)
            ->setAmount($amount)
            ->setName("Tarifa funcional {$counter}");

        $clientPackage = (new CommunicationClientPackage())
            ->setTenant($account)
            ->setPriceClientPackage($pricePackage)
            ->setName("Paquete funcional {$counter}")
            ->setDescription("Paquete funcional {$counter}")
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setActiveEndAt(new \DateTimeImmutable('+1 year'));

        $this->em->persist($product);
        $this->em->persist($pricePackage);
        $this->em->persist($clientPackage);
        $this->em->flush();

        return $clientPackage;
    }

    /**
     * Paquete V2 (catálogo agnóstico de proveedor) + contrato "por defecto"
     * (tenant NULL, vigente) + vínculo explícito a un producto de UN
     * proveedor — listo para venderse vía
     * CommunicationSaleService::admitV2()/admitV2ForReserve()
     * (PackageCatalogResolver resuelve el precio, ProviderDispatchResolver
     * el despacho). Reemplaza a createSellablePackage() (V1, retirada de
     * CommunicationSaleService en la Fase 5 de la deprecación de V1) para
     * los tests funcionales que necesitan una venta completa de punta a
     * punta.
     */
    protected function createSellableV2Package(
        Environment $environment,
        string $provider,
        float $price = 5.0,
        string $currency = 'USD',
        float $destinationAmount = 500.0,
        string $destinationCurrency = 'CUP',
    ): CommunicationPackage {
        static $counter = 0;
        $counter++;

        $package = (new CommunicationPackage())
            ->setName("Paquete V2 funcional {$counter}")
            ->setDescription("Paquete V2 funcional {$counter}")
            ->setDestinationAmount($destinationAmount)
            ->setDestinationCurrency($destinationCurrency);
        $this->em->persist($package);

        $contract = (new CommunicationContract())
            ->addPackage($package)
            ->setDestinationAmount($destinationAmount)
            ->setDestinationCurrency($destinationCurrency)
            ->setPrice($price)
            ->setCurrency($currency);
        $this->em->persist($contract);

        $product = (new CommunicationProduct())
            ->setEnvironment($environment)
            ->setPackageId(300000 + $counter)
            ->setPackageType('RECHARGE')
            ->setPrice($price)
            ->setEnabled(true)
            ->setDescription("Producto V2 funcional {$counter}")
            ->setProvider($provider)
            ->setExternalRef((string) (300000 + $counter));
        $this->em->persist($product);

        $binding = (new CommunicationPackageProviderProduct())
            ->setCommunicationPackage($package)
            ->setProvider($provider)
            ->setProduct($product);
        $this->em->persist($binding);

        $this->em->flush();

        return $package;
    }

    protected function createRouting(
        Client $client,
        string $provider,
        ?Environment $environment = null,
        ?string $saleType = null,
        bool $isActive = true,
        ?int $priority = null,
        ?string $fallbackProvider = null,
        ?string $serviceName = null,
        ?string $subserviceName = null,
    ): ClientProviderRouting {
        $routing = (new ClientProviderRouting())
            ->setClient($client)
            ->setEnvironment($environment)
            ->setSaleType($saleType)
            ->setProvider($provider)
            ->setFallbackProvider($fallbackProvider)
            ->setServiceCategory($serviceName, $subserviceName)
            ->setIsActive($isActive);

        if ($priority !== null) {
            $routing->setPriority($priority);
        }

        $this->em->persist($routing);
        $this->em->flush();

        return $routing;
    }

    /**
     * Fija un valor de sys_config dentro de la transacción del test (se
     * revierte junto con el resto de fixtures en tearDown()).
     */
    protected function setSysConfig(string $propertyName, string $value): void
    {
        $repo = $this->em->getRepository(\App\Entity\SysConfig::class);
        $config = $repo->findOneBy(['propertyName' => $propertyName]);

        if ($config === null) {
            $config = (new \App\Entity\SysConfig())->setPropertyName($propertyName);
        }

        $config->setPropertyValue($value)->setIsActive(true);
        $this->em->persist($config);
        $this->em->flush();

        // El repositorio cachea por propertyName (cache.sys_config): sin
        // invalidar, una lectura previa en el mismo proceso vería el valor
        // viejo.
        $repo->invalidateCache();
    }
}
