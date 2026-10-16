<?php

declare(strict_types=1);

namespace App\Market\GiftCards\Infrastructure\Persistence;

use App\Market\GiftCards\Domain\GiftCard;
use App\Market\GiftCards\Domain\GiftCardBatch;
use App\Market\GiftCards\Domain\GiftCardEvent;
use App\Market\GiftCards\Domain\GiftCardRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineGiftCardRepository implements GiftCardRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function transactional(callable $work): mixed
    {
        return $this->em->wrapInTransaction($work);
    }

    public function nextSerialNumbers(int $count): array
    {
        $numbers = $this->em->getConnection()->fetchFirstColumn(
            "SELECT nextval('market_gift_card_serial_seq') FROM generate_series(1, :n)",
            ['n' => $count],
        );

        return array_map('intval', $numbers);
    }

    public function saveBatch(GiftCardBatch $batch): void
    {
        $this->em->persist($batch);
        $this->em->flush();
    }

    public function findBatch(int $id): ?GiftCardBatch
    {
        return $this->em->find(GiftCardBatch::class, $id);
    }

    public function saveCards(array $cards): void
    {
        foreach ($cards as $card) {
            $this->em->persist($card);
        }
        $this->em->flush();
    }

    public function save(GiftCard $card, ?GiftCardEvent $event = null): void
    {
        $this->em->persist($card);
        if ($event !== null) {
            $this->em->persist($event);
        }
        $this->em->flush();
    }

    public function addEvent(GiftCardEvent $event): void
    {
        $this->em->persist($event);
        $this->em->flush();
    }

    public function findBySerial(string $serial): ?GiftCard
    {
        return $this->em->getRepository(GiftCard::class)->findOneBy(['serial' => strtoupper(trim($serial))]);
    }

    public function findByPinHash(string $hash): ?GiftCard
    {
        return $this->em->getRepository(GiftCard::class)->findOneBy(['pinHash' => $hash]);
    }

    public function search(?int $productId, ?int $batchId, ?string $serialLike): array
    {
        $qb = $this->em->createQueryBuilder()->select('c')->from(GiftCard::class, 'c')->orderBy('c.serial', 'ASC')->setMaxResults(500);
        if ($productId !== null) {
            $qb->andWhere('c.productId = :product')->setParameter('product', $productId);
        }
        if ($batchId !== null) {
            $qb->andWhere('c.batchId = :batch')->setParameter('batch', $batchId);
        }
        if ($serialLike !== null) {
            $qb->andWhere('c.serial LIKE :serial')->setParameter('serial', '%' . addcslashes(strtoupper($serialLike), '%_\\') . '%');
        }

        /** @var list<GiftCard> $cards */
        $cards = $qb->getQuery()->getResult();

        return $cards;
    }

    public function redeemed(int $limit): array
    {
        /** @var list<GiftCard> $cards */
        $cards = $this->em->createQueryBuilder()
            ->select('c')->from(GiftCard::class, 'c')
            ->where('c.redeemedAt IS NOT NULL')
            ->orderBy('c.redeemedAt', 'DESC')->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)->getQuery()->getResult();

        return $cards;
    }

    public function events(int $cardId): array
    {
        /** @var list<GiftCardEvent> $events */
        $events = $this->em->getRepository(GiftCardEvent::class)->findBy(['cardId' => $cardId], ['createdAt' => 'ASC', 'id' => 'ASC']);

        return $events;
    }
}
