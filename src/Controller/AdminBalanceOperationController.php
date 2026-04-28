<?php

namespace App\Controller;

use App\DTO\BalanceInDto;
use App\DTO\Out\BalanceOperationOutDto;
use App\DTO\Out\ExportResultOutDto;
use App\DTO\Out\PaginatedTotalOutDto;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\BalanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/admin/balance-operation')]
class AdminBalanceOperationController extends AbstractController
{
    public function __construct(
        private readonly BalanceService $balanceService,
        private readonly NormalizerInterface $serializer
    ) {

    }

    #[Route(name: 'admin_balance_operation', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar operaciones de balance', tag: 'Balance Operations', responseDto: PaginatedTotalOutDto::class, itemDto: BalanceOperationOutDto::class)]
    public function __invoke(
        #[MapQueryParameter] int $page = 0,
        #[MapQueryParameter] int $limit = 10,
        #[MapQueryParameter] string $orderBy = 'createdAt ASC',
        #[MapQueryParameter] array $filter = [],
    ): JsonResponse {
        $response = $this->balanceService->getBalanceOperations($filter, $orderBy, $page, $limit);
        $results = $this->serializer->normalize($response->getResults(), 'json', context: [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            'groups' => [
                'balance:reading',
            ],
        ]);
        $response->setResults($results);

        return $this->json($response);
    }


    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    #[Route(
        "/platform-balance/{environment}",
        name: 'admin_balance_operation_platform_balance',
        methods: ['GET']
    )]
    #[DashboardEndpoint(summary: 'Balance de plataforma por entorno', tag: 'Balance Operations')]
    public function getPlatformBalance(
        string $environment
    ): JsonResponse
    {
        return $this->json($this->balanceService->getBalancePlatform($environment));
    }

    /**
     * @throws \App\Exception\MyCurrentException
     */
    #[Route("/{id}", name: 'admin_balance_operation_update', methods: ['PUT', 'PATCH'])]
    #[DashboardEndpoint(summary: 'Actualizar operación de balance', tag: 'Balance Operations', requestDto: BalanceInDto::class, responseDto: BalanceOperationOutDto::class)]
    public function update(int $id, BalanceInDto $balance): JsonResponse
    {
        return $this->json(
            $this->serializer->normalize(
                $this->balanceService->update($id, $balance),
                'json',
                [
                    'groups' => [
                        'balance:reading',
                    ],
                ]
            )
        );
    }

    /**
     * @throws \App\Exception\MyCurrentException
     */
    #[Route(name: 'admin_balance_operation_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear operación de balance', tag: 'Balance Operations', requestDto: BalanceInDto::class, responseDto: BalanceOperationOutDto::class, responseStatusCode: 201)]
    public function createBalance(BalanceInDto $balance): JsonResponse
    {
        $newBalance = $this->balanceService->create($balance);
        return $this->json(
            $this->serializer->normalize(
                $newBalance,
                'json',
                [
                    'groups' => [
                        'balance:reading',
                    ],
                ]
            )
        );
    }

    #[Route("/export", name: 'admin_balance_operation_export', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Exportar operaciones de balance', tag: 'Balance Operations', responseDto: ExportResultOutDto::class)]
    public function exportBalance(
        #[MapQueryParameter] int $accountId
    ) {
        $operations = $this->balanceService->exportToExcel($accountId);
        return $this->json($operations);

//        return new Response(
//            $this->serializer->serialize($operations, 'csv'),
//            Response::HTTP_OK,
//            [
//                'Content-Type' => 'application/vnd.ms-excel',
//                'Content-Disposition' => 'attachment; filename="reports.xls"',
//            ]
//        );
    }
}