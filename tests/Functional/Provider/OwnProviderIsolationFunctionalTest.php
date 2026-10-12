<?php

namespace App\Tests\Functional\Provider;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderDomainEnum;
use App\Provider\ProviderRegistry;
use App\Service\Provider\ProviderAvailabilityService;

/**
 * COMR-71 (C2, criterio de aceptación): con OWN registrado, ninguna pantalla, tarea ni resolver de comunicaciones
 * lo lista ni lo elige. Se comprueba con el contenedor real, no con dobles.
 *
 * @coversNothing
 */
class OwnProviderIsolationFunctionalTest extends ProviderFunctionalTestCase
{
    private function registry(): ProviderRegistry
    {
        return self::getContainer()->get(ProviderRegistry::class);
    }

    public function testOwnIsRegisteredButOnlyForTheMarketDomain(): void
    {
        $this->assertTrue($this->registry()->has(CommunicationProviderEnum::OWN));
        $this->assertSame([CommunicationProviderEnum::OWN], $this->registry()->registeredFor(ProviderDomainEnum::Market));
    }

    public function testRegisteredStillListsOnlyTheCommunicationsProviders(): void
    {
        $registered = $this->registry()->registered();

        $this->assertNotContains(CommunicationProviderEnum::OWN, $registered);
        $this->assertEqualsCanonicalizing(
            [CommunicationProviderEnum::ETECSA, CommunicationProviderEnum::DTONE, CommunicationProviderEnum::CSQ],
            $registered,
        );
    }

    public function testTheAvailabilityMatrixNeverListsOwn(): void
    {
        $service = self::getContainer()->get(ProviderAvailabilityService::class);

        $providers = array_unique(array_column($service->statusMatrix(), 'provider'));

        $this->assertNotContains('OWN', $providers);
        $this->assertEqualsCanonicalizing(['ETECSA', 'DTONE', 'CSQ'], $providers);
    }
}
