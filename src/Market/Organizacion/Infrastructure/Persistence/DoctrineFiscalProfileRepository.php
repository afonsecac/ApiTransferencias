<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Persistence;

use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFiscalProfileRepository implements FiscalProfileRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByCountry(string $countryCode): ?FiscalProfile
    {
        return $this->em->find(FiscalProfile::class, $countryCode);
    }

    public function findAll(): array
    {
        /** @var list<FiscalProfile> $profiles */
        $profiles = $this->em->getRepository(FiscalProfile::class)->findBy([], ['countryCode' => 'ASC']);

        // El registro genérico, al final: los países con registro propio primero.
        usort($profiles, static fn (FiscalProfile $a, FiscalProfile $b) => [$a->countryCode() === FiscalProfile::GENERIC_CODE, $a->countryCode()]
            <=> [$b->countryCode() === FiscalProfile::GENERIC_CODE, $b->countryCode()]);

        return $profiles;
    }

    public function save(FiscalProfile $profile): void
    {
        $this->em->persist($profile);
        $this->em->flush();
    }
}
