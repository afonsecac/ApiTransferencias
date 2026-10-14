<?php

declare(strict_types=1);

namespace App\Tests\Market\Organizacion\Domain;

use App\Market\Organizacion\Domain\FiscalField;
use App\Market\Organizacion\Domain\FiscalFieldType;
use App\Market\Organizacion\Domain\InvalidFiscalProfile;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Market\Organizacion\Domain\FiscalField
 */
final class FiscalFieldTest extends TestCase
{
    public function testItRoundTripsThroughAnArray(): void
    {
        $field = new FiscalField(
            key: 'ein',
            label: 'EIN',
            type: FiscalFieldType::TEXT,
            required: true,
            pattern: '^[0-9]{2}-?[0-9]{7}$',
            helpText: 'Employer Identification Number',
            options: [],
            active: true,
            position: 1,
        );

        $this->assertEquals($field, FiscalField::fromArray($field->toArray()));
    }

    /**
     * @dataProvider invalidKeyProvider
     */
    public function testItRejectsAKeyThatIsNotSnakeCase(string $key): void
    {
        $this->expectException(InvalidFiscalProfile::class);

        new FiscalField(key: $key, label: 'X', type: FiscalFieldType::TEXT);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeyProvider(): iterable
    {
        yield 'vacía' => [''];
        yield 'con espacios' => ['tax id'];
        yield 'con mayúsculas' => ['TaxId'];
        yield 'empieza por número' => ['1tax'];
        yield 'demasiado larga' => [str_repeat('a', 61)];
    }

    public function testItRejectsAnEmptyLabel(): void
    {
        $this->expectException(InvalidFiscalProfile::class);

        new FiscalField(key: 'nit', label: '  ', type: FiscalFieldType::TEXT);
    }

    public function testItRejectsAPatternThatDoesNotCompile(): void
    {
        $this->expectException(InvalidFiscalProfile::class);

        new FiscalField(key: 'nit', label: 'NIT', type: FiscalFieldType::TEXT, pattern: '([0-9');
    }

    public function testASelectNeedsAtLeastOneOption(): void
    {
        $this->expectException(InvalidFiscalProfile::class);

        new FiscalField(key: 'form', label: 'Formulario', type: FiscalFieldType::SELECT, options: []);
    }

    public function testOnlyASelectCanHaveOptions(): void
    {
        $this->expectException(InvalidFiscalProfile::class);

        new FiscalField(key: 'nit', label: 'NIT', type: FiscalFieldType::TEXT, options: ['a']);
    }

    public function testDeactivatingReturnsACopyWithoutMutatingTheOriginal(): void
    {
        $field = new FiscalField(key: 'nit', label: 'NIT', type: FiscalFieldType::TEXT);

        $off = $field->deactivated();

        $this->assertTrue($field->active);
        $this->assertFalse($off->active);
        $this->assertSame('nit', $off->key);
    }
}
