<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\DTO\OutputPaginationPackage;
use App\Entity\Account;
use App\Entity\CommunicationPricePackage;
use App\Repository\CommunicationPricePackageRepository;
use App\Service\PackagePriceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;

class CommunicationClientPackageProvider implements ProviderInterface
{

    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $itemProvider,
        #[Autowire(service: 'doctrine.orm.entity_manager')]
        private readonly EntityManagerInterface $em,
        private readonly PackagePriceService  $packagePriceService,
        private readonly Security $security,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $clientPackages = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($clientPackages instanceof \Countable && $clientPackages->count() === 0) {
            $tenant = $this->security->getUser();
            if (!is_null($tenant) && $tenant instanceof Account) {
                /** @var \App\Repository\CommunicationPricePackageRepository $pricePackageRepo */
                $pricePackageRepo = $this->em->getRepository(CommunicationPricePackage::class);
                $packageItems = $pricePackageRepo->getPricesByEnvironment($tenant->getEnvironment()?->getType(), $tenant->getId());
                if (count($packageItems) > 0) {
                    foreach ($packageItems as $packageItem) {
                        $this->packagePriceService->createPackageClient($packageItem, $tenant);
                    }
                } else {
                    $packageItems = $pricePackageRepo->getPricesByEnvironment($tenant->getEnvironment()?->getType());
                    foreach ($packageItems as $packageItem) {
                        $this->packagePriceService->copyPricePackage($packageItem, $tenant);
                    }
                }
                if (count($packageItems) > 0) {
                    $this->em->flush();
                }

                $clientPackages = $this->itemProvider->provide($operation, $uriVariables, $context);
            }
        }

        return $clientPackages;
    }
}
