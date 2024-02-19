<?php

namespace App\Entity;

use App\Repository\CommunicationSaleRechargeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommunicationSaleRechargeRepository::class)]
class CommunicationSaleRecharge extends CommunicationSaleInfo
{
    #[ORM\Column(length: 15)]
    private ?string $phoneNumber = null;

    /**
     * @param string $phoneNumber
     */
    public function __construct(string $phoneNumber)
    {
        $this->phoneNumber = $phoneNumber;
        parent::__construct();
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }
}
