<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Domain;

use App\Market\Organizacion\Domain\CostCenterDraft;
use App\Market\Organizacion\Domain\CostCenterSet;
use App\Market\Organizacion\Domain\InvalidOrgUnit;
use App\Market\Organizacion\Domain\OrgUnit;
use App\Market\Organizacion\Domain\OrgUnitType;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Organizacion\Domain\CostCenterSet
 * @covers \App\Market\Organizacion\Domain\CostCenterDraft
 * @covers \App\Market\Organizacion\Domain\CostCenter
 */
final class CostCenterSetTest extends TestCase
{
    private function unit(): OrgUnit
    {
        return new OrgUnit('Sol', 'Sol S.L.', 'ES', 'B12345678', 'Calle 1', 'a@sol.example', [OrgUnitType::SELLER], []);
    }

    private function set(): CostCenterSet
    {
        return CostCenterSet::start($this->unit(), [new CostCenterDraft('GEN', 'General', true)]);
    }

    public function testItNeedsAtLeastOneCostCenter(): void
    {
        $this->expectException(InvalidOrgUnit::class);

        CostCenterSet::start($this->unit(), []);
    }

    public function testItNeedsExactlyOnePrincipalFromTheStart(): void
    {
        foreach ([
            [new CostCenterDraft('A', 'A', false)],
            [new CostCenterDraft('A', 'A', true), new CostCenterDraft('B', 'B', true)],
        ] as $drafts) {
            try {
                CostCenterSet::start($this->unit(), $drafts);
                $this->fail('Debía lanzar InvalidOrgUnit');
            } catch (InvalidOrgUnit) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testItRejectsRepeatedCodes(): void
    {
        $this->expectException(InvalidOrgUnit::class);

        CostCenterSet::start($this->unit(), [new CostCenterDraft('A', 'A', true), new CostCenterDraft('a', 'Otra A', false)]);
    }

    public function testACodeIsNormalizedAndValidated(): void
    {
        $this->assertSame('CC-01', (new CostCenterDraft(' cc-01 ', 'Uno', true))->code);

        $this->expectException(InvalidOrgUnit::class);
        new CostCenterDraft('con espacios', 'Uno', true);
    }

    public function testANameIsRequired(): void
    {
        $this->expectException(InvalidOrgUnit::class);

        new CostCenterDraft('GEN', '   ', true);
    }

    public function testTheCentersBelongToTheUnitAndTheStartedOneIsThePrincipal(): void
    {
        $unit = $this->unit();
        $set = CostCenterSet::start($unit, [new CostCenterDraft('GEN', 'General', true)]);

        $this->assertSame('GEN', $set->principal()->code());
        $this->assertSame($unit, $set->principal()->orgUnit());
        $this->assertTrue($set->principal()->isActive());
    }

    public function testItAddsAnotherCenterThatIsNotThePrincipal(): void
    {
        $set = $this->set();

        $new = $set->add('almacen', 'Almacén central');

        $this->assertCount(2, $set->all());
        $this->assertFalse($new->isPrincipal());
        $this->assertSame('ALMACEN', $new->code());
        $this->assertSame('GEN', $set->principal()->code());
    }

    public function testItDoesNotAddARepeatedCenter(): void
    {
        $set = $this->set();

        $this->expectException(InvalidOrgUnit::class);
        $set->add('gen', 'Repetido');
    }

    public function testItChangesThePrincipalWithoutEverHavingTwoOrNone(): void
    {
        $set = $this->set();
        $set->add('ALM', 'Almacén');

        $changed = $set->makePrincipal('ALM');

        $this->assertCount(2, $changed);
        $this->assertCount(1, array_filter($set->all(), static fn ($c) => $c->isPrincipal()));
        $this->assertSame('ALM', $set->principal()->code());
    }

    public function testMakingThePrincipalThePrincipalChangesNothing(): void
    {
        $this->assertSame([], $this->set()->makePrincipal('GEN'));
    }

    public function testThePrincipalCannotBeDeactivated(): void
    {
        $this->expectException(InvalidOrgUnit::class);

        $this->set()->deactivate('GEN');
    }

    public function testASecondaryCenterCanBeDeactivatedAndReactivated(): void
    {
        $set = $this->set();
        $set->add('ALM', 'Almacén');

        $set->deactivate('ALM');
        $this->assertFalse($set->get('ALM')->isActive());

        $set->activate('ALM');
        $this->assertTrue($set->get('ALM')->isActive());
    }

    public function testAnInactiveCenterCannotBecomeThePrincipal(): void
    {
        $set = $this->set();
        $set->add('ALM', 'Almacén');
        $set->deactivate('ALM');

        $this->expectException(InvalidOrgUnit::class);
        $set->makePrincipal('ALM');
    }

    public function testItRenamesACenterKeepingItsCodeAndState(): void
    {
        $set = $this->set();

        $set->rename('GEN', 'Central');

        $this->assertSame('Central', $set->get('GEN')->name());
        $this->assertTrue($set->get('GEN')->isPrincipal());
    }

    public function testAnUnknownCenterFails(): void
    {
        $this->expectException(InvalidOrgUnit::class);

        $this->set()->makePrincipal('NOPE');
    }
}
