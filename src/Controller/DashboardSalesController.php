<?php

namespace App\Controller;

use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\SaleCheckStatusOutDto;
use App\DTO\Out\SaleInfoDetailOutDto;
use App\DTO\Out\SaleInfoListOutDto;
use App\DTO\Out\SaleRetryOutDto;
use App\Entity\CommunicationSaleInfo;
use App\Entity\User;
use App\Enums\CommunicationStateEnum;
use App\Message\CheckSaleMessage;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationSaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/packages/sales')]
class DashboardSalesController extends AbstractController
{
    private const SORTABLE_FIELDS = [
        'id' => 's.id',
        'createdAt' => 's.createdAt',
        'state' => 's.state',
        'amount' => 's.amount',
        'totalPrice' => 's.totalPrice',
        'transactionId' => 's.transactionId',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NormalizerInterface $serializer,
        private readonly CommunicationSaleService $saleService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('', name: 'dashboard_sales_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar ventas', tag: 'Sales', responseDto: PaginatedListOutDto::class, itemDto: SaleInfoListOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'createdAt DESC');

        $qb = $this->em->getRepository(CommunicationSaleInfo::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.tenant', 'a')
            ->leftJoin('a.client', 'c')
            ->leftJoin('a.environment', 'e');

        // Filtro por tipo: recharge, sale o ambos
        $type = $request->query->get('type');
        if ($type === 'recharge') {
            $qb->andWhere('s INSTANCE OF App\Entity\CommunicationSaleRecharge');
        } elseif ($type === 'sale') {
            $qb->andWhere('s INSTANCE OF App\Entity\CommunicationSalePackage');
        }

        // Filtro por client id — admins pueden filtrar por cualquier cliente; el resto solo ve el suyo
        $clientId = $request->query->get('clientId');
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ADMIN')) {
            if (!empty($clientId)) {
                $qb->andWhere('c.id = :clientId')->setParameter('clientId', $clientId);
            }
        } elseif ($user instanceof User && $user->getCompany() !== null) {
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $user->getCompany()->getId());
        }

        // Filtro por transaction id del sistema
        $transactionId = $request->query->get('transactionId');
        if (!empty($transactionId)) {
            $qb->andWhere('s.transactionId LIKE :txId')
                ->setParameter('txId', '%' . $transactionId . '%');
        }

        // Filtro por estado
        $state = $request->query->get('state');
        if (!empty($state)) {
            $qb->andWhere('s.state = :state')->setParameter('state', $state);
        }

        // Filtro por rango de fechas
        $dateFrom = $request->query->get('dateFrom');
        if (!empty($dateFrom)) {
            $qb->andWhere('s.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }
        $dateTo = $request->query->get('dateTo');
        if (!empty($dateTo)) {
            $qb->andWhere('s.createdAt <= :dateTo')
                ->setParameter('dateTo', (new \DateTimeImmutable($dateTo))->modify('+1 day'));
        }

        // Filtro por environment type
        $envType = $request->query->get('environmentType');
        if (!empty($envType)) {
            $qb->andWhere('e.type = :envType')->setParameter('envType', $envType);
        }

        // Orden
        $orderParts = explode(' ', $orderBy);
        $field = self::SORTABLE_FIELDS[$orderParts[0]] ?? 's.createdAt';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)
            ->setFirstResult($page * $limit)
            ->getQuery()
            ->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        $normalized = $this->serializer->normalize($results, 'json', [
            'groups' => ['sale:list'],
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
        // Añadir type desde el discriminator (no se serializa automáticamente con grupos custom)
        foreach ($normalized as $i => $item) {
            $normalized[$i]['type'] = $results[$i] instanceof \App\Entity\CommunicationSaleRecharge ? 'recharge' : 'sale';
        }

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $hasNext,
            'hasPrevious' => $page > 0,
            'results' => $normalized,
        ]);
    }

    #[Route('/{id}', name: 'dashboard_sales_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Detalle de venta', tag: 'Sales', responseDto: SaleInfoDetailOutDto::class)]
    public function detail(int $id): JsonResponse
    {
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($id);
        if ($sale === null) {
            return $this->json(['error' => ['message' => 'Sale not found']], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && $user instanceof User && $user->getCompany() !== null) {
            if ($sale->getTenant()?->getClient()?->getId() !== $user->getCompany()->getId()) {
                return $this->json(['error' => ['message' => 'Sale not found']], Response::HTTP_NOT_FOUND);
            }
        }

        $data = $this->serializer->normalize($sale, 'json', [
            'groups' => ['sale:detail'],
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
        $data['type'] = $sale instanceof \App\Entity\CommunicationSaleRecharge ? 'recharge' : 'sale';

        return $this->json($data);
    }

    #[Route('/{id}/check-status', name: 'dashboard_sales_check_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Verificar estado de venta', tag: 'Sales', responseDto: SaleCheckStatusOutDto::class)]
    public function checkStatus(int $id): JsonResponse
    {
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($id);
        if ($sale === null) {
            return $this->json(['error' => ['message' => 'Sale not found']], Response::HTTP_NOT_FOUND);
        }

        if ($sale->getState() !== CommunicationStateEnum::PENDING) {
            return $this->json([
                'id' => $sale->getId(),
                'state' => $sale->getState()->value,
                'message' => 'Sale is not in pending state, no check needed.',
            ]);
        }

        $stateProcess = $sale->getStateProcess();
        if ($stateProcess === null
            || $stateProcess === CommunicationStateEnum::CREATED->value
            || $stateProcess === 'SENDING'
        ) {
            return $this->json([
                'id' => $sale->getId(),
                'state' => $sale->getState()->value,
                'stateProcess' => $stateProcess,
                'message' => 'Sale has not been sent to the provider yet. Please wait.',
            ]);
        }

        try {
            $updated = $this->saleService->checkSaleInfo($sale->getId());
            return $this->json([
                'id' => $updated->getId(),
                'state' => $updated->getState()->value,
                'transactionStatus' => $updated->getTransactionStatus(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => ['message' => $e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/retry', name: 'dashboard_sales_retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Reintentar venta fallida', tag: 'Sales', responseDto: SaleRetryOutDto::class)]
#[IsGranted('ROLE_API_ADMIN')]
    public function retry(int $id): JsonResponse
    {
        $sale = $this->em->getRepository(CommunicationSaleInfo::class)->find($id);
        if ($sale === null) {
            return $this->json(['error' => ['message' => 'Sale not found']], Response::HTTP_NOT_FOUND);
        }

        $allowedStates = [CommunicationStateEnum::PENDING, CommunicationStateEnum::FAILED];
        if (!in_array($sale->getState(), $allowedStates, true)) {
            return $this->json([
                'error' => ['message' => 'Retry only allowed for Pending or Failed sales.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->messageBus->dispatch(new CheckSaleMessage($sale->getId()));

        return $this->json([
            'id' => $sale->getId(),
            'state' => $sale->getState()->value,
            'retryDispatched' => true,
        ]);
    }
}
