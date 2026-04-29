<?php

namespace App\Service;

use App\DTO\CreateCommunicationPriceDto;
use App\DTO\UpdateCommunicationPriceDto;
use App\Entity\CommunicationPrice;
use App\Exception\MyCurrentException;
use Doctrine\ORM\EntityManagerInterface;

class CommunicationPriceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** @throws MyCurrentException */
    public function create(CreateCommunicationPriceDto $dto): CommunicationPrice
    {
        $cp = new CommunicationPrice();
        $cp->setStartPrice($dto->getStartPrice());
        $cp->setAmount($dto->getAmount());
        $cp->setCurrencyPrice(strtoupper($dto->getCurrencyPrice() ?? 'CUP'));
        $cp->setCurrency(strtoupper($dto->getCurrency() ?? 'USD'));
        $cp->setIsActive($dto->getIsActive() ?? true);
        $cp->setValidStartAt(new \DateTimeImmutable($dto->getValidStartAt() ?? 'now'));
        if ($dto->getEndPrice() !== null) {
            $cp->setEndPrice($dto->getEndPrice());
        }
        if ($dto->getValidEndAt() !== null) {
            $cp->setValidEndAt(new \DateTimeImmutable($dto->getValidEndAt()));
        }

        $this->em->persist($cp);
        $this->em->flush();

        return $cp;
    }

    public function update(CommunicationPrice $cp, UpdateCommunicationPriceDto $dto): CommunicationPrice
    {
        if ($dto->getStartPrice() !== null) {
            $cp->setStartPrice($dto->getStartPrice());
        }
        if ($dto->getEndPrice() !== null) {
            $cp->setEndPrice($dto->getEndPrice());
        }
        if ($dto->getCurrencyPrice() !== null) {
            $cp->setCurrencyPrice(strtoupper($dto->getCurrencyPrice()));
        }
        if ($dto->getAmount() !== null) {
            $cp->setAmount($dto->getAmount());
        }
        if ($dto->getCurrency() !== null) {
            $cp->setCurrency(strtoupper($dto->getCurrency()));
        }
        if ($dto->getIsActive() !== null) {
            $cp->setIsActive($dto->getIsActive());
        }
        if ($dto->getValidStartAt() !== null) {
            $cp->setValidStartAt(new \DateTimeImmutable($dto->getValidStartAt()));
        }
        if ($dto->getValidEndAt() !== null) {
            $cp->setValidEndAt(new \DateTimeImmutable($dto->getValidEndAt()));
        }

        $this->em->flush();

        return $cp;
    }

    public function toggle(CommunicationPrice $cp): CommunicationPrice
    {
        $cp->setIsActive(!$cp->isActive());
        $this->em->flush();

        return $cp;
    }

    public function delete(CommunicationPrice $cp): void
    {
        $this->em->remove($cp);
        $this->em->flush();
    }
}
