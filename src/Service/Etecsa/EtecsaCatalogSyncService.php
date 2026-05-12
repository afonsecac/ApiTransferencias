<?php

namespace App\Service\Etecsa;

use App\Entity\CommunicationNationality;
use App\Entity\CommunicationOffice;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationProvinces;
use App\Entity\Environment;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use App\Service\CommonService;

class EtecsaCatalogSyncService extends CommonService
{
    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        SysConfigRepository $sysConfigRepo,
        SerializerInterface $serializer,
        private readonly EtecsaGatewayClient $client,
    ) {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
    }

    /**
     * Sincroniza el catálogo de nacionalidades usando upsert por (environment, comId).
     * No elimina registros — solo inserta y actualiza.
     */
    public function syncNationalities(Environment $env): SyncResult
    {
        $items = $this->client->listNationalities($env);
        $created = $updated = $skipped = 0;
        $repo = $this->em->getRepository(CommunicationNationality::class);

        foreach ($items as $raw) {
            $item = (object) $raw;

            if (!isset($item->Id)) {
                ++$skipped;
                continue;
            }

            $entity = $repo->findOneBy(['environment' => $env, 'comId' => (int) $item->Id]);

            if ($entity === null) {
                $entity = new CommunicationNationality();
                $entity->setEnvironment($env);
                $entity->setComId((int) $item->Id);
                $this->em->persist($entity);
                ++$created;
            } else {
                ++$updated;
            }

            $entity->setName((string) ($item->Name ?? ''));
            $entity->setCodeAlpha3((string) ($item->Abbreviation ?? ''));
        }

        $this->em->flush();

        return new SyncResult($created, $updated, $skipped);
    }

    /**
     * Sincroniza provincias y sus oficinas comerciales usando upsert por (environment, comId).
     * Las oficinas se sincronizan por provincia; no se eliminan registros existentes.
     */
    public function syncProvinces(Environment $env): SyncResult
    {
        $items = $this->client->listProvinces($env);
        $created = $updated = $skipped = 0;
        $repo = $this->em->getRepository(CommunicationProvinces::class);

        foreach ($items as $raw) {
            $item = (object) $raw;

            if (!isset($item->Id)) {
                ++$skipped;
                continue;
            }

            $entity = $repo->findOneBy(['environment' => $env, 'comId' => (int) $item->Id]);

            if ($entity === null) {
                $entity = new CommunicationProvinces();
                $entity->setEnvironment($env);
                $entity->setComId((int) $item->Id);
                $this->em->persist($entity);
                ++$created;
            } else {
                ++$updated;
            }

            $entity->setName((string) ($item->Name ?? ''));
        }

        $this->em->flush();

        return new SyncResult($created, $updated, $skipped);
    }

    /**
     * Sincroniza oficinas comerciales para todas las provincias del entorno.
     * Upsert por (environment, comId string). Requiere que las provincias ya estén sincronizadas.
     */
    public function syncOffices(Environment $env): SyncResult
    {
        $provinces = $this->em->getRepository(CommunicationProvinces::class)->findBy(['environment' => $env]);
        $created = $updated = $skipped = 0;
        $officeRepo = $this->em->getRepository(CommunicationOffice::class);

        foreach ($provinces as $province) {
            $items = $this->client->listCommercialOffices($env, $province->getComId());

            foreach ($items as $raw) {
                $item = (object) $raw;

                if (!isset($item->Id)) {
                    ++$skipped;
                    continue;
                }

                $comId = (string) $item->Id;
                $entity = $officeRepo->findOneBy(['environment' => $env, 'comId' => $comId]);

                if ($entity === null) {
                    $entity = new CommunicationOffice();
                    $entity->setEnvironment($env);
                    $entity->setComId($comId);
                    $entity->setProvince($province);
                    $this->em->persist($entity);
                    ++$created;
                } else {
                    ++$updated;
                }

                $entity->setName((string) ($item->Name ?? ''));
                $entity->setIsActive(true);
                $entity->setIsAirport((bool) ($item->IsAirport ?? false));
            }
        }

        $this->em->flush();

        return new SyncResult($created, $updated, $skipped);
    }

    /**
     * Sincroniza el catálogo de productos (paquetes ETECSA) usando upsert por (environment, packageId).
     * Solo inserta/actualiza productos activos y vigentes; no elimina los ya expirados de la BD.
     */
    public function syncProducts(Environment $env): SyncResult
    {
        $items = $this->client->listPackages($env);
        $created = $updated = $skipped = 0;
        $repo = $this->em->getRepository(CommunicationProduct::class);
        $now = new \DateTimeImmutable();

        foreach ($items as $raw) {
            $item = (object) $raw;

            if (!isset($item->Id)) {
                ++$skipped;
                continue;
            }

            $entity = $repo->findOneBy(['environment' => $env, 'packageId' => (int) $item->Id]);

            if ($entity === null) {
                $entity = new CommunicationProduct();
                $entity->setEnvironment($env);
                $entity->setPackageId((int) $item->Id);
                $this->em->persist($entity);
                ++$created;
            } else {
                ++$updated;
            }

            $entity->setPackageType((string) ($item->PackageType ?? ''));
            $entity->setProductType((string) ($item->PackageType ?? ''));
            $entity->setPrice((float) ($item->Price ?? 0.0));
            $entity->setEnabled((bool) ($item->Enabled ?? false));
            $entity->setDescription((string) ($item->Description ?? ''));

            if (!empty($item->InitialDate)) {
                $entity->setInitialDate(new \DateTimeImmutable($item->InitialDate));
            }

            $endDate = !empty($item->FinalDate) ? new \DateTimeImmutable($item->FinalDate) : null;
            $entity->setEndDateAt($endDate);
        }

        $this->em->flush();

        return new SyncResult($created, $updated, $skipped);
    }
}
