<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Environment;
use App\Entity\Permission;
use App\Entity\User;
use App\Repository\EnvironmentRepository;
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

    /**
     * @param EntityManagerInterface $em
     * @param Security $security
     * @param ParameterBagInterface $parameters
     * @param MailerInterface $mailer
     * @param LoggerInterface $logger
     * @param UserPasswordHasherInterface $passwordHasher
     * @param EnvironmentRepository $environmentRepository
     */
    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository
    ) {
        $this->em = $em;
        $this->security = $security;
        $this->parameters = $parameters;
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->passwordHasher = $passwordHasher;
        $this->environment = $environmentRepository;
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
        $environment = new Environment();
        $environment->setBasePath("");
        $environment->setClientId("696ee5fc-9128-44ec-ab1a-3cb43871804b");
        $environment->setScope("api://148563e0-e668-4f39-a441-49c6a9373dbf/.default");
        $environment->setIsActive(true);
        $environment->setClientSecret("AN18Q~CYbT20zAvIYJTnKME1XPdUXMFKJgzyZbQu");
        $environment->setTenantId("c602b45a-7c38-4b4d-a2c3-8c41dfa957ca");
        $environment->setType("TEST");
        $environment->setProviderName("RebusPay");

        $this->em->persist($environment);

        $company = new Client();
        $company->setCompanyName("Comremit Solutions SL");
        $company->setCompanyCountry("ESP");
        $company->setCompanyAddress("Pasaje del Carme, 24, Sant Cugat del Vallés, 08173, Barcelona, España");
        $company->setCompanyIdentification("B10583565");
        $company->setCompanyIdentificationType("CIF");
        $company->setCompanyEmail("support@comremit.com");
        $company->setCompanyPhoneNumber("+34");
        $company->setDiscountOfClient(0);

        $this->em->persist($company);

        $permission = new Permission();
        $permission->setDiscount(0);
        $permission->setCommission(0);
        $permission->setDiscountUnit("%");
        $permission->setClient($company);
        $permission->setEnvironment($environment);
        $permission->setIsActive(true);
        $permission->setIsActiveAt(new \DateTimeImmutable('now'));
        $permission->setOrigin("*");

        $this->em->persist($permission);

//        $this->em->flush();
    }


}
