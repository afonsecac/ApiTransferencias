<?php

namespace App\Service\Provider;

use App\DTO\UpdateProviderCredentialsDto;
use App\Entity\SysConfig;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderRegistry;
use App\Repository\SysConfigRepository;
use App\Service\SysConfigCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administra las credenciales de proveedor en sys_config bajo el esquema
 * `provider.{code}.{type}.{campo}` que ya lee ProviderCredentialsResolver
 * (src/Provider/ProviderCredentialsResolver.php). Los campos válidos y
 * cuáles se cifran los declara cada proveedor en
 * CommunicationProviderInterface::getConfigSchema() — no hay un esquema fijo
 * común (ver ProviderConfigField).
 */
class ProviderCredentialsAdminService
{
    private const ENVIRONMENT_TYPES = ['TEST', 'PROD'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SysConfigRepository $sysConfigRepo,
        private readonly ProviderRegistry $registry,
        private readonly ProviderCredentialsResolver $credentialsResolver,
        #[Autowire('%env(string:default::SYS_CONFIG_ENCRYPTION_KEY)%')]
        private readonly string $encryptionKey = '',
    ) {
    }

    /**
     * @return array{
     *     test: array<string, array{key: string, label: string, required: bool, secret: bool, value: ?string, configured: bool}>,
     *     prod: array<string, array{key: string, label: string, required: bool, secret: bool, value: ?string, configured: bool}>,
     *     isFullyConfiguredTest: bool,
     *     isFullyConfiguredProd: bool,
     *     activeTest: bool,
     *     activeProd: bool,
     * }
     */
    public function getStatus(CommunicationProviderEnum $provider): array
    {
        $schema = $this->registry->get($provider)->getConfigSchema();

        $status = [];
        foreach (self::ENVIRONMENT_TYPES as $environmentType) {
            $prefix = $this->keyPrefix($provider, $environmentType);
            $fields = [];

            foreach ($schema as $field) {
                $row = $this->sysConfigRepo->findOneBy(['propertyName' => $prefix . $field->key, 'isActive' => true]);
                $rawValue = $row?->getPropertyValue();
                $configured = $rawValue !== null && $rawValue !== '';

                $fields[$field->key] = [
                    'key' => $field->key,
                    'label' => $field->label,
                    'required' => $field->required,
                    'secret' => $field->secret,
                    // El valor de un campo secreto NUNCA se devuelve, ni cifrado ni en claro.
                    'value' => $field->secret ? null : $rawValue,
                    'configured' => $configured,
                ];
            }

            $status[strtolower($environmentType)] = $fields;
        }

        $status['isFullyConfiguredTest'] = $this->credentialsResolver->isFullyConfigured($provider, 'TEST');
        $status['isFullyConfiguredProd'] = $this->credentialsResolver->isFullyConfigured($provider, 'PROD');
        $status['activeTest'] = $this->credentialsResolver->isActive($provider, 'TEST');
        $status['activeProd'] = $this->credentialsResolver->isActive($provider, 'PROD');

        return $status;
    }

    /**
     * Interruptor manual `provider.{code}.{type}.active` — no forma parte
     * del esquema del proveedor (getConfigSchema()), es una bandera
     * administrativa aparte. No se cifra: no es un secreto.
     */
    public function setActive(CommunicationProviderEnum $provider, string $environmentType, bool $active): void
    {
        $this->setValue($this->keyPrefix($provider, $environmentType) . 'active', $active ? '1' : '0', encrypted: false);
        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();
    }

    public function upsert(CommunicationProviderEnum $provider, string $environmentType, UpdateProviderCredentialsDto $dto): void
    {
        $schema = $this->registry->get($provider)->getConfigSchema();
        $schemaByKey = [];
        foreach ($schema as $field) {
            $schemaByKey[$field->key] = $field;
        }

        $prefix = $this->keyPrefix($provider, $environmentType);

        foreach ($dto->getValues() ?? [] as $key => $value) {
            if (!isset($schemaByKey[$key])) {
                throw new MyCurrentException(
                    'PROVIDER_CONFIG_UNKNOWN_FIELD',
                    "El proveedor {$provider->value} no tiene un campo de configuración llamado '{$key}'",
                    Response::HTTP_BAD_REQUEST,
                );
            }

            $field = $schemaByKey[$key];
            if ($field->secret) {
                $this->assertEncryptionKeyAvailable();
            }
            $this->setValue($prefix . $key, (string) $value, encrypted: $field->secret);
        }

        $this->em->flush();
        $this->sysConfigRepo->invalidateCache();
    }

    private function setValue(string $propertyName, string $plainValue, bool $encrypted): void
    {
        $config = $this->sysConfigRepo->findOneBy(['propertyName' => $propertyName]);
        if ($config === null) {
            $config = new SysConfig();
            $config->setPropertyName($propertyName);
            $config->setIsActive(true);
            $this->em->persist($config);
        }

        $config->setIsEncrypted($encrypted);
        $config->setPropertyValue($encrypted ? SysConfigCipher::encrypt($plainValue, $this->encryptionKey) : $plainValue);
    }

    private function keyPrefix(CommunicationProviderEnum $provider, string $environmentType): string
    {
        return sprintf('provider.%s.%s.', strtolower($provider->value), strtolower($environmentType));
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
