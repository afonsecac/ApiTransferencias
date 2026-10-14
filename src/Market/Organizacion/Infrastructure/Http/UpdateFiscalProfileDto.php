<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de PUT /dashboard/api/market/fiscal-profiles/{countryCode}: nombre y lista COMPLETA de campos
 * (un campo que ya existía no puede faltar: se desactiva, no se borra).
 */
class UpdateFiscalProfileDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    protected ?string $name;

    /** @var FiscalFieldDto[]|null */
    #[Assert\NotNull]
    #[Assert\Valid]
    protected ?array $fields;

    /**
     * @param FiscalFieldDto[]|null $fields
     */
    public function __construct(?string $name = null, ?array $fields = null)
    {
        $this->name = $name;
        $this->fields = $fields;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return FiscalFieldDto[]|null
     */
    public function getFields(): ?array
    {
        return $this->fields;
    }

    /**
     * @param FiscalFieldDto[]|null $fields
     */
    public function setFields(?array $fields): void
    {
        $this->fields = $fields;
    }
}
