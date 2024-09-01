<?php

namespace App\Service;

use App\Entity\ConfigureSequence;
use App\Repository\ConfigureSequenceRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ConfigureSequenceService extends CommonService
{
    public function __construct(EntityManagerInterface $em, Security $security, ParameterBagInterface $parameters, MailerInterface $mailer, LoggerInterface $logger, UserPasswordHasherInterface $passwordHasher, EnvironmentRepository $environmentRepository, SysConfigRepository $sysConfigRepo, SerializerInterface $serializer, private readonly ConfigureSequenceRepository $sequenceRepository)
    {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
    }


    public function getLastSequence(string $operationClass): int
    {
        $currentDate = new \DateTimeImmutable('now');
        $year = $currentDate->format('Y');
        $sequence = $this->sequenceRepository->findOneBy([
            'year' => $year,
            'operationClass' => $operationClass
        ]);
        if (is_null($sequence)) {
            $sequence = new ConfigureSequence($operationClass, $year);
            $this->em->persist($sequence);
        }
        $sequence->setSequenceValue($sequence->getSequenceValue() + 1);
        $this->em->flush();

        return $sequence->getSequenceValue();
    }

}
