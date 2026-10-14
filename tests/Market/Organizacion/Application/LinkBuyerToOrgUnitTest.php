<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Application;

use App\Market\Organizacion\Application\LinkBuyerToOrgUnit;
use App\Market\Organizacion\Domain\Buyer;
use App\Market\Organizacion\Domain\BuyerNotFound;
use App\Market\Organizacion\Domain\OrgUnitType;
use App\Market\Organizacion\Domain\UnknownBuyerCountry;
use PHPUnit\Framework\TestCase;

/**
 * COMR-272: cada comprador (Client) queda referenciado a una unidad organizativa de tipo comprador con un
 * centro de costo principal que lleva su nombre.
 *
 * @covers \App\Market\Organizacion\Application\LinkBuyerToOrgUnit
 */
final class LinkBuyerToOrgUnitTest extends TestCase
{
    private InMemoryOrgUnitRepository $units;
    private InMemoryBuyerRegistry $buyers;
    private LinkBuyerToOrgUnit $link;

    protected function setUp(): void
    {
        $this->units = new InMemoryOrgUnitRepository();
        $this->buyers = new InMemoryBuyerRegistry();
        $this->link = new LinkBuyerToOrgUnit($this->units, $this->buyers);
    }

    private function buyer(int $id = 1, string $name = 'AWS Malta Ltd', string $country = 'MLT', string $identification = ' c63841 ', ?string $address = 'St Julians, Malta'): Buyer
    {
        $buyer = new Buyer($id, $name, $country, $identification, $address, 'ops@aws-malta.example');
        $this->buyers->add($buyer);

        return $buyer;
    }

    public function testItCreatesABuyerUnitFromTheClientData(): void
    {
        $this->buyer();

        $details = ($this->link)(1);

        $unit = $details->unit;
        $this->assertSame([OrgUnitType::BUYER], $unit->types());
        $this->assertSame('MT', $unit->countryCode());
        $this->assertSame('C63841', $unit->taxId());
        $this->assertSame('AWS Malta Ltd', $unit->name());
        $this->assertSame('AWS Malta Ltd', $unit->legalName());
        $this->assertSame('St Julians, Malta', $unit->address());
        $this->assertSame('ops@aws-malta.example', $unit->billingEmail());
        $this->assertSame([], $unit->fiscalData());
    }

    public function testItGivesTheUnitOnePrincipalCostCenterNamedAfterTheClient(): void
    {
        $this->buyer();

        $details = ($this->link)(1);

        $this->assertCount(1, $details->costCenters);
        $this->assertTrue($details->costCenters[0]->isPrincipal());
        $this->assertTrue($details->costCenters[0]->isActive());
        $this->assertSame('AWS Malta Ltd', $details->costCenters[0]->name());
    }

    public function testItLinksTheClientToTheUnit(): void
    {
        $this->buyer();

        $details = ($this->link)(1);

        $this->assertSame($details->unit->id(), $this->buyers->orgUnitIdOf(1));
    }

    public function testItIsIdempotentForAClientAlreadyLinked(): void
    {
        $this->buyer();

        $first = ($this->link)(1);
        $second = ($this->link)(1);

        $this->assertSame($first->unit->id(), $second->unit->id());
        $this->assertCount(1, $this->units->units);
        $this->assertSame(1, $this->units->saves);
    }

    public function testTwoClientsWithTheSameTaxIdShareTheUnitAndItKeepsTheBuyerTypeOnce(): void
    {
        $this->buyer(1, 'Comremit Solutions SL', 'ESP', 'B10583565');
        $this->buyer(2, 'Comremit Solutions SL (NIF)', 'ESP', 'b10583565');

        $a = ($this->link)(1);
        $b = ($this->link)(2);

        $this->assertSame($a->unit->id(), $b->unit->id());
        $this->assertCount(1, $this->units->units);
        $this->assertSame([OrgUnitType::BUYER], $b->unit->types());
    }

    public function testItAddsTheBuyerTypeToAnExistingUnitWithoutLosingItsOtherTypes(): void
    {
        $this->buyer(1, 'Alisney AG Cuba', 'CUB', 'B10583565');
        $existing = $this->units;
        $unit = new \App\Market\Organizacion\Domain\OrgUnit(
            'Alisney', 'Alisney AG', 'CU', 'B10583565', 'La Habana', 'a@alisney.example', [OrgUnitType::SELLER], [],
        );
        $existing->save($unit, []);

        $details = ($this->link)(1);

        $this->assertSame($unit->id(), $details->unit->id());
        $this->assertEqualsCanonicalizing([OrgUnitType::SELLER, OrgUnitType::BUYER], $details->unit->types());
        $this->assertCount(1, $this->units->units);
    }

    public function testAClientWithoutAddressGetsAPlaceholderUntilItIsEdited(): void
    {
        $this->buyer(address: null);

        $details = ($this->link)(1);

        $this->assertNotSame('', $details->unit->address());
    }

    public function testItRejectsAClientWhoseCountryIsNotRecognisedAndCreatesNothing(): void
    {
        $this->buyer(country: 'STJ');

        try {
            ($this->link)(1);
            $this->fail('Debió rechazar el país desconocido.');
        } catch (UnknownBuyerCountry) {
            $this->assertSame([], $this->units->units);
            $this->assertNull($this->buyers->orgUnitIdOf(1));
        }
    }

    public function testItTrimsLongNamesAndAddressesToWhatTheUnitCanStore(): void
    {
        $this->buyer(name: str_repeat('N', 255), address: str_repeat('D', 600));

        $details = ($this->link)(1);

        $this->assertSame(160, mb_strlen($details->unit->name()));
        $this->assertSame(200, mb_strlen($details->unit->legalName()));
        $this->assertSame(300, mb_strlen($details->unit->address()));
        $this->assertSame(120, mb_strlen($details->costCenters[0]->name()));
    }

    public function testItFailsForAClientThatDoesNotExist(): void
    {
        $this->expectException(BuyerNotFound::class);

        ($this->link)(99);
    }
}
