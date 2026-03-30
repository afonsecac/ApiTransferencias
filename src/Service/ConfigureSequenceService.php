<?php

namespace App\Service;

use App\Entity\ConfigureSequence;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ConfigureSequenceService extends CommonService
{
    public function __construct(EntityManagerInterface $em, Security $security, ParameterBagInterface $parameters, MailerInterface $mailer, LoggerInterface $logger, UserPasswordHasherInterface $passwordHasher, EnvironmentRepository $environmentRepository, SysConfigRepository $sysConfigRepo, SerializerInterface $serializer)
    {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
    }

    public function getLastSequence(string $operationClass): int
    {
        $currentDate = new \DateTimeImmutable('now');
        $year = $currentDate->format('Y');

        $this->em->beginTransaction();
        try {
            // Lock pesimista: SELECT ... FOR UPDATE previene lecturas concurrentes
            $sequence = $this->em->createQueryBuilder()
                ->select('s')
                ->from(ConfigureSequence::class, 's')
                ->where('s.year = :year')
                ->andWhere('s.operationClass = :class')
                ->setParameter('year', $year)
                ->setParameter('class', $operationClass)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            if ($sequence === null) {
                $sequence = new ConfigureSequence($operationClass, $year);
                $this->em->persist($sequence);
            }

            $sequence->setSequenceValue($sequence->getSequenceValue() + 1);
            $this->em->flush();
            $this->em->commit();

            return $sequence->getSequenceValue();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }
}
