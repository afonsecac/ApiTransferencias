<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Account;
use App\Entity\Beneficiary;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class CreateBeneficiaryProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly CityRepository $cityRepository
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $user = $this->security->getUser();
        if ($data instanceof Beneficiary && $user instanceof Account) {
            $city = $this->cityRepository->find($data->getCityOfResidenceId());
            $data->setTenant($user);
            $data->setEnvironment($user->getEnvironment());
            $data->setCity($city);
            $data->setIsActive(false);

            $this->em->persist($data);
            $this->em->flush();
        }
        return $data;
    }
}
