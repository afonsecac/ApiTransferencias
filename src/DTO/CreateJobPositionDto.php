<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateJobPositionDto implements IInput
{
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    protected ?string $code = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    protected ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['MANAGEMENT', 'TECHNOLOGY', 'FINANCE', 'MARKETING'])]
    protected ?string $area = null;

    public function __construct(
        ?string $code = null,
        ?string $name = null,
        ?string $area = null,
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->area = $area;
    }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $v): void { $this->code = $v; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $v): void { $this->area = $v; }
}
