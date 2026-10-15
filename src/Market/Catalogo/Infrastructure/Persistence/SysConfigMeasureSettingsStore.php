<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Persistence;

use App\Entity\SysConfig;
use App\Market\Catalogo\Domain\MeasureSettingsStore;
use App\Market\Catalogo\Domain\MeasureUnit;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Guarda las medidas en la configuración del sistema (las mismas tres claves que lee SysConfigMeasureSettings) y vacía la
 * caché para que la lectura siguiente vea el valor nuevo.
 */
final class SysConfigMeasureSettingsStore implements MeasureSettingsStore
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $config,
    ) {
    }

    public function save(MeasureUnit $weightBase, MeasureUnit $volumeBase, int $volumetricDivisor): void
    {
        $this->set(SysConfigMeasureSettings::WEIGHT_BASE_KEY, $weightBase->value);
        $this->set(SysConfigMeasureSettings::VOLUME_BASE_KEY, $volumeBase->value);
        $this->set(SysConfigMeasureSettings::DIVISOR_KEY, (string) $volumetricDivisor);
        $this->em->flush();
        $this->config->invalidateCache();
    }

    private function set(string $key, string $value): void
    {
        $config = $this->config->findOneBy(['propertyName' => $key]) ?? (new SysConfig())->setPropertyName($key);
        $config->setPropertyValue($value)->setIsActive(true);
        $this->em->persist($config);
    }
}
