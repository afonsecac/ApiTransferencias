<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Environment;
use App\Entity\Account;
use App\Entity\User;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class CommonService
{
    protected EntityManagerInterface $em;
    protected Security $security;
    protected ParameterBagInterface $parameters;
    protected Serializer $serializer;
    protected MailerInterface $mailer;
    protected LoggerInterface $logger;
    protected UserPasswordHasherInterface $passwordHasher;
    protected EnvironmentRepository  $environment;
    protected SysConfigRepository $sysConfigRepo;

    /**
     * @param EntityManagerInterface $em
     * @param Security $security
     * @param ParameterBagInterface $parameters
     * @param MailerInterface $mailer
     * @param LoggerInterface $logger
     * @param UserPasswordHasherInterface $passwordHasher
     * @param EnvironmentRepository $environmentRepository
     * @param SysConfigRepository $sysConfigRepo
     */
    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        SysConfigRepository $sysConfigRepo
    ) {
        $this->em = $em;
        $this->security = $security;
        $this->parameters = $parameters;
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->passwordHasher = $passwordHasher;
        $this->environment = $environmentRepository;
        $this->sysConfigRepo = $sysConfigRepo;
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizer = [new DateTimeNormalizer(), new DateIntervalNormalizer(), new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizer, $encoders);
    }

    public function onCreatedAdmin(): void {
        $user = new User();
        $user->setFirstName("Administrador");
        $user->setLastName("Sistema");
        $user->setEmail("support@sendmundo.com");
        $password = $this->passwordHasher->hashPassword($user, 'admin1234!');
        $user->setPassword($password);
        $user->setIsActive(true);
        $user->setIsCheckValidation(true);
        $user->setIsActiveAt(new \DateTimeImmutable('now'));
        $user->setIsCheckValidationAt(new \DateTimeImmutable('now'));
        $user->setPermission(['ROLE_SUPER_ADMIN']);
        $user->setRoles(['ROLE_SUPPER_ADMIN']);

        $this->em->persist($user);
        $this->em->flush();
    }

    public function onCreateCompany(): void {
        $envSandbox = new Environment();
        $envSandbox->setBasePath("https://demo.rebuspay.com");
        $envSandbox->setClientId("696ee5fc-9128-44ec-ab1a-3cb43871804b");
        $envSandbox->setScope("api://148563e0-e668-4f39-a441-49c6a9373dbf/.default");
        $envSandbox->setIsActive(true);
        $envSandbox->setIsActiveAt(new \DateTimeImmutable('now'));
        $envSandbox->setClientSecret("AN18Q~CYbT20zAvIYJTnKME1XPdUXMFKJgzyZbQu");
        $envSandbox->setTenantId("c602b45a-7c38-4b4d-a2c3-8c41dfa957ca");
        $envSandbox->setType("TEST");
        $envSandbox->setProviderName("RebusPay");

        $this->em->persist($envSandbox);

        $envProd = new Environment();
        $envProd->setBasePath("https://www.rebuspay.com");
        $envProd->setClientId("696ee5fc-9128-44ec-ab1a-3cb43871804b");
        $envProd->setScope("api://148563e0-e668-4f39-a441-49c6a9373dbf/.default");
        $envProd->setIsActive(false);
        $envProd->setClientSecret("AN18Q~CYbT20zAvIYJTnKME1XPdUXMFKJgzyZbQu");
        $envProd->setTenantId("c602b45a-7c38-4b4d-a2c3-8c41dfa957ca");
        $envProd->setType("PROD");
        $envProd->setProviderName("RebusPay");

        $this->em->persist($envProd);

        $company = new Client();
        $company->setCompanyName("Comremit Solutions SL");
        $company->setCompanyCountry("ESP");
        $company->setCompanyAddress("Pasaje del Carme, 24, Sant Cugat del Vallés, 08173, Barcelona, España");
        $company->setCompanyZipCode("08173");
        $company->setCompanyIdentification("B10583565");
        $company->setCompanyIdentificationType("CIF");
        $company->setCompanyEmail("support@comremit.com");
        $company->setCompanyPhoneNumber("+34602027541");
        $company->setDiscountOfClient(0);

        $this->em->persist($company);

        $accountSandbox = new Account();
        $accountSandbox->setDiscount(0);
        $accountSandbox->setCommission(0);
        $accountSandbox->setDiscountUnit("%");
        $accountSandbox->setClient($company);
        $accountSandbox->setEnvironment($envSandbox);
        $accountSandbox->setIsActive(true);
        $accountSandbox->setIsActiveAt(new \DateTimeImmutable('now'));
        $accountSandbox->setOrigin("*");
        $accountSandbox->setAccountId(11);
        $accountSandbox->setEnvironmentName("SANDBOX");

        $this->em->persist($accountSandbox);

        $accountProd = new Account();
        $accountProd->setDiscount(0);
        $accountProd->setCommission(0);
        $accountProd->setDiscountUnit("%");
        $accountProd->setClient($company);
        $accountProd->setEnvironment($envProd);
        $accountProd->setIsActive(false);
        $accountProd->setIsActiveAt(new \DateTimeImmutable('now'));
        $accountProd->setOrigin("*");
        $accountProd->setEnvironmentName("PROD");

        $this->em->persist($accountProd);

        $this->em->flush();
    }


}
