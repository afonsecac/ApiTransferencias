<?php

namespace App\Service;

use App\DTO\CreateSysConfigDto;
use App\DTO\UpdateSysConfigDto;
use App\Entity\SysConfig;
use App\Exception\MyCurrentException;
use App\Market\Catalogo\Domain\ProductRepository;
use App\Repository\SysConfigRepository;
use App\Service\SysConfigCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

class SysConfigAdminService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly ProductRepository $marketProducts,
        #[Autowire('%env(string:default::SYS_CONFIG_ENCRYPTION_KEY)%')]
        private readonly string $encryptionKey = '',
    ) {}

    public function create(CreateSysConfigDto $dto): SysConfig
    {
        $existing = $this->sysConfigRepo->findOneBy(['propertyName' => $dto->getPropertyName()]);
        if ($existing !== null) {
            throw new MyCurrentException(
                'SYS_CONFIG_DUPLICATE',
                "Ya existe una variable con el nombre '{$dto->getPropertyName()}'.",
                Response::HTTP_CONFLICT
            );
        }

        $isEncrypted = $dto->getIsEncrypted() ?? false;
        $value = $dto->getPropertyValue();
        if ($isEncrypted) {
            $this->assertEncryptionKeyAvailable();
            $value = SysConfigCipher::encrypt($value, $this->encryptionKey);
        }

        $config = new SysConfig();
        $config->setPropertyName($dto->getPropertyName());
        $config->setPropertyValue($value);
        $config->setIsEncrypted($isEncrypted);
        $config->setIsActive($dto->getIsActive() ?? true);
        $config->setClients($dto->getClients());

        $this->em->persist($config);
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();

        return $config;
    }

    public function update(SysConfig $config, UpdateSysConfigDto $dto): SysConfig
    {
        if ($dto->getPropertyName() !== null || $dto->getPropertyValue() !== null || $dto->getIsActive() !== null || $dto->getIsEncrypted() !== null) {
            $this->assertMeasureBaseNotLocked($config);
        }
        if ($dto->getPropertyName() !== null && $dto->getPropertyName() !== $config->getPropertyName()) {
            $existing = $this->sysConfigRepo->findOneBy(['propertyName' => $dto->getPropertyName()]);
            if ($existing !== null) {
                throw new MyCurrentException(
                    'SYS_CONFIG_DUPLICATE',
                    "Ya existe una variable con el nombre '{$dto->getPropertyName()}'.",
                    Response::HTTP_CONFLICT
                );
            }
            $config->setPropertyName($dto->getPropertyName());
        }

        $currentIsEncrypted = $config->isEncrypted();
        $newIsEncrypted = $dto->getIsEncrypted() ?? $currentIsEncrypted;

        if ($dto->getPropertyValue() !== null) {
            $value = $dto->getPropertyValue();
            if ($newIsEncrypted) {
                $this->assertEncryptionKeyAvailable();
                $value = SysConfigCipher::encrypt($value, $this->encryptionKey);
            }
            $config->setPropertyValue($value);
        } elseif ($newIsEncrypted !== $currentIsEncrypted) {
            // Cambio de modo sin nuevo valor: re-cifrar o descifrar el valor existente
            $stored = $config->getPropertyValue() ?? '';
            $this->assertEncryptionKeyAvailable();
            if ($newIsEncrypted) {
                $config->setPropertyValue(SysConfigCipher::encrypt($stored, $this->encryptionKey));
            } else {
                $config->setPropertyValue(SysConfigCipher::decrypt($stored, $this->encryptionKey));
            }
        }

        if ($dto->getIsEncrypted() !== null) {
            $config->setIsEncrypted($newIsEncrypted);
        }

        if ($dto->getIsActive() !== null) {
            $config->setIsActive($dto->getIsActive());
        }

        if ($dto->getClients() !== null) {
            $config->setClients($dto->getClients());
        }

        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();

        return $config;
    }

    public function toggle(SysConfig $config): SysConfig
    {
        $this->assertMeasureBaseNotLocked($config);
        $config->setIsActive(!$config->isActive());
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();

        return $config;
    }

    public function delete(SysConfig $config): void
    {
        $this->assertMeasureBaseNotLocked($config);
        $config->setRemovedAt(new \DateTimeImmutable('now'));
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();
    }

    /**
     * Las unidades base de las medidas del mercado (market.measure.weight_base y volume_base) no se tocan desde aquí mientras haya
     * productos con cantidades: lo guardado se reinterpretaría. La pantalla «Unidades de medida» explica y aplica la misma regla.
     */
    private function assertMeasureBaseNotLocked(SysConfig $config): void
    {
        if (!in_array($config->getPropertyName(), ['market.measure.weight_base', 'market.measure.volume_base'], true)) {
            return;
        }
        if ($this->marketProducts->hasPhysicalProducts()) {
            throw new MyCurrentException(
                'MEASURE_SETTINGS_LOCKED',
                'Las unidades base no se pueden cambiar mientras haya productos con cantidades guardadas: sus datos se reinterpretarían.',
                Response::HTTP_CONFLICT
            );
        }
    }

    private function assertEncryptionKeyAvailable(): void
    {
        if ($this->encryptionKey === '') {
            throw new MyCurrentException(
                'SYS_CONFIG_ENCRYPTION_KEY_MISSING',
                'La variable de entorno SYS_CONFIG_ENCRYPTION_KEY no está configurada.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
