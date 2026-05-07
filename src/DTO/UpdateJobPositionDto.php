<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateJobPositionDto implements IInput
{
    #[Assert\Length(max: 100)]
    protected ?string $name = null;

    #[Assert\Choice(choices: ['MANAGEMENT', 'TECHNOLOGY', 'FINANCE', 'MARKETING'])]
    protected ?string $area = null;

    public function __construct(
        ?string $name = null,
        ?string $area = null,
    ) {
        $this->name = $name;
        $this->area = $area;
    }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): void { $this->name = $v; }

    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $v): void { $this->area = $v; }
}
