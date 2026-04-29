<?php

namespace App\Controller;

use App\DTO\CreateCommunicationPriceDto;
use App\DTO\UpdateCommunicationPriceDto;
use App\DTO\Out\CommunicationPriceOutDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ToggleOutDto;
use App\Entity\CommunicationPrice;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationPriceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
class DashboardCommunicationPriceController extends AbstractController
{
    private const SORTABLE = [
        'id'           => 'cp.id',
        'startPrice'   => 'cp.startPrice',
        'amount'       => 'cp.amount',
        'validStartAt' => 'cp.validStartAt',
        'validEndAt'   => 'cp.validEndAt',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly CommunicationPriceService $priceService,
    ) {
    }

    #[Route('/client/communication-prices', name: 'dashboard_communication_prices_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar communication prices', tag: 'Communication Prices', responseDto: PaginatedListOutDto::class, itemDto: CommunicationPriceOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page  = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $orderBy = $request->query->get('orderBy', 'id DESC');

        $qb = $this->em->getRepository(CommunicationPrice::class)
            ->createQueryBuilder('cp');

        if ($request->query->has('isActive')) {
            $qb->andWhere('cp.isActive = :isActive')
               ->setParameter('isActive', filter_var($request->query->get('isActive'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($currencyPrice = $request->query->get('currencyPrice')) {
            $qb->andWhere('cp.currencyPrice = :currencyPrice')
               ->setParameter('currencyPrice', strtoupper($currencyPrice));
        }
        if ($currency = $request->query->get('currency')) {
            $qb->andWhere('cp.currency = :currency')
               ->setParameter('currency', strtoupper($currency));
        }

        $orderParts = explode(' ', $orderBy);
        $field     = self::SORTABLE[$orderParts[0]] ?? 'cp.id';
        $direction = strtoupper($orderParts[1] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($field, $direction);

        $results = $qb->setMaxResults($limit + 1)->setFirstResult($page * $limit)
            ->getQuery()->getResult();

        $hasNext = count($results) > $limit;
        if ($hasNext) {
            array_pop($results);
        }

        return $this->json([
            'limit'       => $limit,
            'currentPage' => $page,
            'hasNext'     => $hasNext,
            'hasPrevious' => $page > 0,
            'results'     => array_map(fn($cp) => $this->serialize($cp), $results),
        ]);
    }

    #[Route('/client/communication-prices/{id}', name: 'dashboard_communication_prices_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener communication price', tag: 'Communication Prices', responseDto: CommunicationPriceOutDto::class)]
    public function show(int $id): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationPrice::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Not found']], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serialize($cp));
    }

    #[Route('/client/communication-prices', name: 'dashboard_communication_prices_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear communication price', tag: 'Communication Prices', requestDto: CreateCommunicationPriceDto::class, responseDto: CommunicationPriceOutDto::class, responseStatusCode: 201)]
    public function create(CreateCommunicationPriceDto $dto): JsonResponse
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        try {
            $cp = $this->priceService->create($dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serialize($cp), Response::HTTP_CREATED);
    }

    #[Route('/client/communication-prices/{id}', name: 'dashboard_communication_prices_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar communication price', tag: 'Communication Prices', requestDto: UpdateCommunicationPriceDto::class, responseDto: CommunicationPriceOutDto::class)]
    public function update(int $id, UpdateCommunicationPriceDto $dto): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationPrice::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Not found']], Response::HTTP_NOT_FOUND);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        $cp = $this->priceService->update($cp, $dto);

        return $this->json($this->serialize($cp));
    }

    #[Route('/client/communication-prices/{id}/toggle', name: 'dashboard_communication_prices_toggle', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar communication price', tag: 'Communication Prices', responseDto: ToggleOutDto::class)]
    public function toggle(int $id): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationPrice::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Not found']], Response::HTTP_NOT_FOUND);
        }

        $this->priceService->toggle($cp);

        return $this->json(['id' => $cp->getId(), 'isActive' => $cp->isActive()]);
    }

    #[Route('/client/communication-prices/{id}', name: 'dashboard_communication_prices_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Eliminar communication price', tag: 'Communication Prices', responseDto: DeletedOutDto::class)]
    public function delete(int $id): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationPrice::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Not found']], Response::HTTP_NOT_FOUND);
        }

        $this->priceService->delete($cp);

        return $this->json(['deleted' => true]);
    }

    private function serialize(CommunicationPrice $cp): array
    {
        return [
            'id'           => $cp->getId(),
            'startPrice'   => $cp->getStartPrice(),
            'endPrice'     => $cp->getEndPrice(),
            'currencyPrice' => $cp->getCurrencyPrice(),
            'amount'       => $cp->getAmount(),
            'currency'     => $cp->getCurrency(),
            'isActive'     => $cp->isActive(),
            'validStartAt' => $cp->getValidStartAt()?->format('c'),
            'validEndAt'   => $cp->getValidEndAt()?->format('c'),
            'createdAt'    => $cp->getCreatedAt()?->format('c'),
            'updatedAt'    => $cp->getUpdatedAt()?->format('c'),
        ];
    }
}
