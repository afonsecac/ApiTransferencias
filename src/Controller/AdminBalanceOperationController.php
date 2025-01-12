<?php

namespace App\Controller;

use App\Service\BalanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/admin/balance-operation')]
class AdminBalanceOperationController extends AbstractController
{
    public function __construct(
        private readonly BalanceService $balanceService,
        private readonly SerializerInterface $serializer
    ) {

    }

    #[Route(name: 'admin_balance_operation', methods: ['GET'])]
    public function __invoke(
        #[MapQueryParameter] int $page = 0,
        #[MapQueryParameter] int $limit = 10,
        #[MapQueryParameter] string $orderBy = 'createdAt ASC',
        #[MapQueryParameter] array $filters = [],
    ): JsonResponse {
        $response = $this->balanceService->getBalanceOperations($filters, $orderBy, $page, $limit);
        $results = $this->serializer->normalize($response->getResults(), 'json', context: [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            'groups' => [
                'balance:reading'
            ]
        ]);
        $response->setResults($results);

        return $this->json($response);
    }
}