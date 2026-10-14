<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\DTO\IInput;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body de POST /dashboard/api/market/fiscal-profiles.
 */
class CreateFiscalProfileDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z]{2}$/', message: 'El código de país son dos letras mayúsculas (ZZ para el genérico).')]
    protected ?string $countryCode;

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
    public function __construct(?string $countryCode = null, ?string $name = null, ?array $fields = null)
    {
        $this->countryCode = $countryCode;
        $this->name = $name;
        $this->fields = $fields;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): void
    {
        $this->countryCode = $countryCode;
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
