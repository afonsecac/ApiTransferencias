<?php

namespace App\DTO;

use App\OpenApi\Attribute\OAProperty;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Alta manual de producto(s) de catálogo a partir de un identificador del
 * proveedor, sin llamar a su API — ver ManualProductBuilderRegistry para
 * qué proveedores tienen implementación (hoy CSQ y ETECSA) y
 * ManualProductBuilderInterface::getFormSchema() para la forma exacta de
 * `values` según el proveedor elegido (consultable vía
 * GET /dashboard/api/products/manual/schema?provider=...).
 */
class CreateManualProductDto implements IInput
{
    #[Assert\NotBlank]
    protected ?string $provider;

    #[Assert\NotNull]
    #[Assert\Positive]
    protected ?int $environmentId;

    #[Assert\NotNull]
    #[OAProperty(schema: [
        'type' => 'object',
        'additionalProperties' => true,
        'description' => 'Valores del formulario, específicos de cada proveedor — ver getFormSchema() del builder correspondiente.',
    ])]
    protected ?array $values;

    public function __construct(
        ?string $provider = null,
        ?int $environmentId = null,
        ?array $values = null,
    ) {
        $this->provider      = $provider;
        $this->environmentId = $environmentId;
        $this->values        = $values;
    }

    public function getProvider(): ?string { return $this->provider; }
    public function setProvider(?string $v): void { $this->provider = $v; }

    public function getEnvironmentId(): ?int { return $this->environmentId; }
    public function setEnvironmentId(?int $v): void { $this->environmentId = $v; }

    /** @return array<string, mixed>|null */
    public function getValues(): ?array { return $this->values; }
    /** @param array<string, mixed>|null $v */
    public function setValues(?array $v): void { $this->values = $v; }
}
