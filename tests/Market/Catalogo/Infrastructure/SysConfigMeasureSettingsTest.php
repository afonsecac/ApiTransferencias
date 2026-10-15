<?php

declare(strict_types=1);

namespace App\Tests\Market\Catalogo\Infrastructure;

use App\Market\Catalogo\Domain\MeasureSettings;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Market\Catalogo\Infrastructure\Persistence\MeasureSettingsMisconfigured;
use App\Market\Catalogo\Infrastructure\Persistence\SysConfigMeasureSettings;
use App\Repository\SysConfigRepository;
use App\Tests\Functional\Provider\ProviderFunctionalTestCase;

/**
 * COMR-281: las unidades base de peso y volumen y el divisor del peso volumétrico salen de la configuración del sistema. A
 * diferencia de los límites de imágenes no hay valor por defecto silencioso: si falta o es inválido, el sistema falla en voz
 * alta, porque interpretar unos datos guardados en kg como si fueran gramos los corrompería sin que nadie lo notara.
 *
 * @covers \App\Market\Catalogo\Infrastructure\Persistence\SysConfigMeasureSettings
 */
final class SysConfigMeasureSettingsTest extends ProviderFunctionalTestCase
{
    private function settings(): MeasureSettings
    {
        return new SysConfigMeasureSettings(self::getContainer()->get(SysConfigRepository::class));
    }

    private function configure(string $weight = 'kg', string $volume = 'l', string $divisor = '6000'): void
    {
        $this->setSysConfig('market.measure.weight_base', $weight);
        $this->setSysConfig('market.measure.volume_base', $volume);
        $this->setSysConfig('market.measure.volumetric_divisor', $divisor);
    }

    public function testItReadsTheBaseUnitsAndTheDivisorFromTheConfiguration(): void
    {
        $this->configure();

        $this->assertSame(MeasureUnit::KG, $this->settings()->weightBase());
        $this->assertSame(MeasureUnit::L, $this->settings()->volumeBase());
        $this->assertSame(6000, $this->settings()->volumetricDivisor());
    }

    public function testTheBaseUnitsAreWhateverUnitOfTheRightKindTheConfigurationSays(): void
    {
        $this->configure('g', 'ml', '5000');

        $this->assertSame(MeasureUnit::G, $this->settings()->weightBase());
        $this->assertSame(MeasureUnit::ML, $this->settings()->volumeBase());
        $this->assertSame(5000, $this->settings()->volumetricDivisor());
    }

    public function testAMissingKeyFailsLoudlyInsteadOfAssumingADefault(): void
    {
        $this->em->getConnection()->executeStatement("DELETE FROM sys_config WHERE property_name = 'market.measure.weight_base'");
        $this->em->getRepository(\App\Entity\SysConfig::class)->invalidateCache();

        $this->expectException(MeasureSettingsMisconfigured::class);
        $this->settings()->weightBase();
    }

    /**
     * @dataProvider wrongBaseUnits
     */
    public function testAWeightBaseThatIsNotAWeightUnitIsRejected(string $value): void
    {
        $this->configure($value);

        $this->expectException(MeasureSettingsMisconfigured::class);
        $this->settings()->weightBase();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongBaseUnits(): iterable
    {
        yield 'un volumen' => ['l'];
        yield 'un conteo' => ['unit'];
        yield 'una unidad que no existe' => ['stone'];
        yield 'vacío' => [''];
    }

    public function testAVolumeBaseThatIsNotAVolumeUnitIsRejected(): void
    {
        $this->configure('kg', 'kg');

        $this->expectException(MeasureSettingsMisconfigured::class);
        $this->settings()->volumeBase();
    }

    /**
     * @dataProvider wrongDivisors
     */
    public function testTheDivisorMustBeAPositiveInteger(string $value): void
    {
        $this->configure('kg', 'l', $value);

        $this->expectException(MeasureSettingsMisconfigured::class);
        $this->settings()->volumetricDivisor();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongDivisors(): iterable
    {
        yield 'cero' => ['0'];
        yield 'negativo' => ['-6000'];
        yield 'decimal' => ['6000.5'];
        yield 'texto' => ['seis mil'];
        yield 'vacío' => [''];
    }
}
