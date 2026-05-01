<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserDto implements IInput
{
    #[Assert\Email]
    protected ?string $email;

    #[Assert\Length(max: 60)]
    protected ?string $firstName;

    #[Assert\Length(max: 120)]
    protected ?string $lastName;

    #[Assert\Length(max: 60)]
    protected ?string $middleName;

    #[Assert\Length(max: 255)]
    protected ?string $jobTitle;

    #[Assert\Length(max: 20)]
    protected ?string $phoneNumber;

    protected ?string $role;

    #[Assert\Length(min: 8)]
    protected ?string $password;

    protected ?int $companyId;

    public function __construct(
        ?string $email = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $middleName = null,
        ?string $jobTitle = null,
        ?string $phoneNumber = null,
        ?string $role = null,
        ?string $password = null,
        ?int $companyId = null,
    ) {
        $this->email       = $email;
        $this->firstName   = $firstName;
        $this->lastName    = $lastName;
        $this->middleName  = $middleName;
        $this->jobTitle    = $jobTitle;
        $this->phoneNumber = $phoneNumber;
        $this->role        = $role;
        $this->password    = $password;
        $this->companyId   = $companyId;
    }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $v): void { $this->email = $v; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $v): void { $this->firstName = $v; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $v): void { $this->lastName = $v; }

    public function getMiddleName(): ?string { return $this->middleName; }
    public function setMiddleName(?string $v): void { $this->middleName = $v; }

    public function getJobTitle(): ?string { return $this->jobTitle; }
    public function setJobTitle(?string $v): void { $this->jobTitle = $v; }

    public function getPhoneNumber(): ?string { return $this->phoneNumber; }
    public function setPhoneNumber(?string $v): void { $this->phoneNumber = $v; }

    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $v): void { $this->role = $v; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $v): void { $this->password = $v; }

    public function getCompanyId(): ?int { return $this->companyId; }
    public function setCompanyId(?int $v): void { $this->companyId = $v; }
}
