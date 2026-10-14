<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Domain;

use App\Market\Organizacion\Domain\FiscalField;
use App\Market\Organizacion\Domain\FiscalFieldCannotBeRemoved;
use App\Market\Organizacion\Domain\FiscalFieldType;
use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\InvalidFiscalProfile;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Organizacion\Domain\FiscalProfile
 */
final class FiscalProfileTest extends TestCase
{
    private function usProfile(): FiscalProfile
    {
        return new FiscalProfile('US', 'Estados Unidos', [
            new FiscalField('ein', 'EIN', FiscalFieldType::TEXT, required: true, pattern: '^[0-9]{2}-?[0-9]{7}$'),
            new FiscalField('estado', 'Estado', FiscalFieldType::TEXT, required: true, pattern: '^[A-Z]{2}$'),
            new FiscalField('formulario', 'Formulario fiscal', FiscalFieldType::SELECT, options: ['W-9', 'W-8BEN-E']),
        ]);
    }

    public function testAProfileIsIdentifiedByAnUppercaseCountryCode(): void
    {
        $this->assertSame('US', $this->usProfile()->countryCode());

        $this->expectException(InvalidFiscalProfile::class);
        new FiscalProfile('us', 'Estados Unidos', []);
    }

    public function testTheGenericProfileUsesTheReservedCode(): void
    {
        $generic = FiscalProfile::generic();

        $this->assertSame(FiscalProfile::GENERIC_CODE, $generic->countryCode());
        $this->assertSame([], $generic->validate(['cualquier' => 'cosa']));
    }

    public function testItRejectsTwoFieldsWithTheSameKey(): void
    {
        $this->expectException(InvalidFiscalProfile::class);

        new FiscalProfile('CU', 'Cuba', [
            new FiscalField('nit', 'NIT', FiscalFieldType::TEXT),
            new FiscalField('nit', 'NIT otra vez', FiscalFieldType::TEXT),
        ]);
    }

    public function testValidDataHasNoErrors(): void
    {
        $errors = $this->usProfile()->validate(['ein' => '12-3456789', 'estado' => 'NY', 'formulario' => 'W-9']);

        $this->assertSame([], $errors);
    }

    public function testAMissingRequiredFieldIsReported(): void
    {
        $errors = $this->usProfile()->validate(['estado' => 'NY']);

        $this->assertSame(['ein' => 'required'], $errors);
    }

    /**
     * @dataProvider emptyValueProvider
     */
    public function testAnEmptyValueCountsAsMissing(mixed $empty): void
    {
        $this->assertSame(['ein' => 'required'], $this->usProfile()->validate(['ein' => $empty, 'estado' => 'NY']));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function emptyValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'cadena vacía' => [''];
        yield 'solo espacios' => ['   '];
    }

    public function testAValueThatDoesNotMatchThePatternIsReported(): void
    {
        $errors = $this->usProfile()->validate(['ein' => '123', 'estado' => 'New York']);

        $this->assertSame(['ein' => 'format', 'estado' => 'format'], $errors);
    }

    public function testAnOptionalFieldLeftEmptyIsFine(): void
    {
        $this->assertSame([], $this->usProfile()->validate(['ein' => '12-3456789', 'estado' => 'NY']));
    }

    public function testASelectValueMustBeOneOfTheOptions(): void
    {
        $errors = $this->usProfile()->validate(['ein' => '12-3456789', 'estado' => 'NY', 'formulario' => 'W-4']);

        $this->assertSame(['formulario' => 'option'], $errors);
    }

    public function testNumberAndDateFieldsAreTypeChecked(): void
    {
        $profile = new FiscalProfile('PA', 'Panamá', [
            new FiscalField('dv', 'Dígito verificador', FiscalFieldType::NUMBER),
            new FiscalField('inscrito', 'Fecha de inscripción', FiscalFieldType::DATE),
        ]);

        $this->assertSame([], $profile->validate(['dv' => '12', 'inscrito' => '2026-10-14']));
        $this->assertSame(['dv' => 'type', 'inscrito' => 'type'], $profile->validate(['dv' => 'doce', 'inscrito' => '14/10/2026']));
        $this->assertSame(['inscrito' => 'type'], $profile->validate(['inscrito' => '2026-02-30']));
    }

    public function testANonScalarValueIsATypeError(): void
    {
        $this->assertSame(['ein' => 'type'], $this->usProfile()->validate(['ein' => ['x'], 'estado' => 'NY']));
    }

    public function testAnInactiveFieldIsNeitherRequiredNorValidated(): void
    {
        $profile = new FiscalProfile('US', 'Estados Unidos', [
            (new FiscalField('ein', 'EIN', FiscalFieldType::TEXT, required: true, pattern: '^[0-9]{9}$'))->deactivated(),
        ]);

        $this->assertSame([], $profile->validate([]));
        $this->assertSame([], $profile->validate(['ein' => 'lo que sea']));
    }

    public function testKeysThatTheProfileDoesNotKnowAreIgnored(): void
    {
        // Datos ya guardados de campos desactivados (o de versiones anteriores del registro) no se pierden ni fallan.
        $this->assertSame([], $this->usProfile()->validate(['ein' => '12-3456789', 'estado' => 'NY', 'campo_viejo' => 'x']));
    }

    public function testFieldsCanBeReplacedAddingEditingAndDeactivating(): void
    {
        $profile = $this->usProfile();

        $profile->replaceFields([
            new FiscalField('ein', 'EIN (nuevo texto)', FiscalFieldType::TEXT, required: true),
            (new FiscalField('estado', 'Estado', FiscalFieldType::TEXT))->deactivated(),
            new FiscalField('formulario', 'Formulario fiscal', FiscalFieldType::SELECT, options: ['W-9', 'W-8BEN-E']),
            new FiscalField('telefono', 'Teléfono', FiscalFieldType::TEXT),
        ]);

        $fields = $profile->fields();
        $this->assertCount(4, $fields);
        $this->assertSame('EIN (nuevo texto)', $fields[0]->label);
        $this->assertFalse($fields[1]->active);
    }

    public function testAFieldCannotBeRemovedOnlyDeactivated(): void
    {
        $profile = $this->usProfile();

        $this->expectException(FiscalFieldCannotBeRemoved::class);
        $this->expectExceptionMessage('estado');

        $profile->replaceFields([
            new FiscalField('ein', 'EIN', FiscalFieldType::TEXT),
            new FiscalField('formulario', 'Formulario fiscal', FiscalFieldType::SELECT, options: ['W-9']),
        ]);
    }

    public function testFieldsAreKeptInTheirDeclaredPositionOrder(): void
    {
        $profile = new FiscalProfile('CU', 'Cuba', [
            new FiscalField('b', 'B', FiscalFieldType::TEXT, position: 2),
            new FiscalField('a', 'A', FiscalFieldType::TEXT, position: 1),
        ]);

        $this->assertSame(['a', 'b'], array_map(static fn (FiscalField $f) => $f->key, $profile->fields()));
    }
}
