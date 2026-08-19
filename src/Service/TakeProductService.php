<?php

namespace App\Service;

use App\Exception\MyCurrentException;
use App\Repository\EnvironmentRepository;
use App\Enums\CommunicationProviderEnum;
use App\Repository\SysConfigRepository;
use App\Service\Etecsa\EtecsaGeoCatalogSyncService;
use App\Service\Provider\CommunicationCatalogSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @deprecated Usar CommunicationCatalogSyncService/EtecsaGeoCatalogSyncService directamente.
 * Los métodos takeProduct() y takeOtherData() delegan en ellos.
 * Este servicio se mantiene para compatibilidad con app:takeOther:command y app:takeProduct.
 */
class TakeProductService extends CommonService
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
        private readonly EtecsaGeoCatalogSyncService $geoCatalogSyncService,
        private readonly CommunicationCatalogSyncService $catalogSyncService,
    ) {
        parent::__construct($em, $security, $parameters, $mailer, $logger, $passwordHasher, $environmentRepository, $sysConfigRepo, $serializer);
    }


    /**
     * @deprecated Usar CommunicationCatalogSyncService::syncProducts()
     */
    public function takeProduct(string $env): array
    {
        try {
            $environments = $this->environmentRepository->findBy([
                'scope' => 'ET',
                'isActive' => true,
                'type' => $env,
            ]);

            $items = 0;
            foreach ($environments as $item) {
                $result = $this->catalogSyncService->syncProducts(CommunicationProviderEnum::ETECSA, $item);
                $items += $result->created;
            }

            return ['items' => $items, 'isProcessed' => true];
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $codeExc = $exception->getCode() ? (string) $exception->getCode() : 'Unknown error';
            throw new MyCurrentException($codeExc, $exception->getMessage());
        }
    }

    /**
     * @deprecated Usar EtecsaGeoCatalogSyncService::syncNationalities() / syncProvinces() / syncOffices()
     */
    public function takeOtherData(): void
    {
        $environments = $this->environmentRepository->findBy([
            'scope' => 'ET',
            'isActive' => true,
        ]);

        foreach ($environments as $item) {
            $this->geoCatalogSyncService->syncNationalities($item);
            $this->geoCatalogSyncService->syncProvinces($item);
            $this->geoCatalogSyncService->syncOffices($item);
        }
    }

}
