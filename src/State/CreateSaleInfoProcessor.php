<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DTO\ReserveRecharge;
use App\Entity\Account;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSalePackage;
use App\Entity\CommunicationSaleRecharge;
use App\Exception\MyCurrentException;
use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class CreateSaleInfoProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommunicationSaleService $saleService,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly RateLimiterFactory $apiRechargeLimiter,
    ) {
    }

    /**
     * @inheritDoc
     * @param mixed $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return CommunicationSaleInfo|null
     * @throws \App\Exception\MyCurrentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CommunicationSaleInfo | null
    {
        $user = $this->security->getUser();
        if ($user instanceof Account) {
            $limit = $this->apiRechargeLimiter->create((string) $user->getId())->consume(1);
            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp(), 'Too many recharge requests. Please wait before trying again.');
            }
        }

        if ($data instanceof CommunicationSaleInfo && $data->getClientTransactionId() !== null) {
            $existing = $this->em->getRepository(CommunicationSaleInfo::class)
                ->findOneBy(['clientTransactionId' => $data->getClientTransactionId()]);
            if ($existing !== null) {
                throw new ConflictHttpException('A transaction with this clientTransactionId already exists.');
            }
        }

        $communicationSale = null;
        if ($data instanceof CommunicationSaleRecharge) {
            $communicationSale = $this->saleService->processRecharge($data);
        } else if ($data instanceof CommunicationSalePackage) {
            $communicationSale = $this->saleService->executeSale($data);
        } else if ($data instanceof ReserveRecharge) {
            $communicationSale = $this->saleService->processReserve($data);
        }
        return $communicationSale;
    }
}
