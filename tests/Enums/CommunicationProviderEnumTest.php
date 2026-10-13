<?php

namespace App\Tests\Enums;

use App\Enums\CommunicationProviderEnum;
use App\Enums\ProviderDomainEnum;
use PHPUnit\Framework\TestCase;

/**
 * COMR-71 (C2, ADR-001): OWN es el proveedor propio del mercado. Existe como código, pero solo opera en el
 * dominio de mercado: no se puede elegir en el enrutado de comunicaciones.
 *
 * @covers \App\Enums\CommunicationProviderEnum
 */
class CommunicationProviderEnumTest extends TestCase
{
    public function testOwnIsTheOwnMarketProviderCode(): void
    {
        $this->assertSame('OWN', CommunicationProviderEnum::OWN->value);
        $this->assertSame(CommunicationProviderEnum::OWN, CommunicationProviderEnum::from('OWN'));
    }

    public function testTheCodeFitsTheTwentyCharacterProviderColumns(): void
    {
        foreach (CommunicationProviderEnum::cases() as $case) {
            $this->assertLessThanOrEqual(20, strlen($case->value));
        }
    }

    /**
     * @dataProvider communicationsProviders
     */
    public function testTheExistingProvidersOperateOnlyInCommunications(CommunicationProviderEnum $provider): void
    {
        $this->assertSame([ProviderDomainEnum::Communications], $provider->domains());
    }

    /**
     * @return iterable<string, array{CommunicationProviderEnum}>
     */
    public static function communicationsProviders(): iterable
    {
        yield 'ETECSA' => [CommunicationProviderEnum::ETECSA];
        yield 'DTONE' => [CommunicationProviderEnum::DTONE];
        yield 'CSQ' => [CommunicationProviderEnum::CSQ];
    }

    public function testOwnOperatesOnlyInTheMarketDomain(): void
    {
        $this->assertSame([ProviderDomainEnum::Market], CommunicationProviderEnum::OWN->domains());
    }

    public function testValuesStillListsEveryCodeButCommunicationsValuesExcludesOwn(): void
    {
        $this->assertContains('OWN', CommunicationProviderEnum::values());
        $this->assertSame(['ETECSA', 'DTONE', 'CSQ'], CommunicationProviderEnum::communicationsValues());
    }
}
