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
        $tenant = $this->security->getUser();

        if ($clientPackages->count() === 0) {
            $packageItems = $this->em->getRepository(CommunicationPricePackage::class)->findBy([
                'tenant' => null
            ], ['price' => 'ASC']);
            foreach ($packageItems as $packageItem) {
                $this->packagePriceService->copyPricePackage($packageItem, $tenant);
            }
            $this->em->flush();

            $clientPackages = $this->itemProvider->provide($operation, $uriVariables, $context);
        }

        return $clientPackages;
    }
}
