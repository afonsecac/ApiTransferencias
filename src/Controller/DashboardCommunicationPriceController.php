<?php

namespace App\Controller;

use App\DTO\Out\CommunicationPriceOutDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\ToggleOutDto;
use App\Entity\CommunicationPrice;
use App\OpenApi\Attribute\DashboardEndpoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    #[DashboardEndpoint(summary: 'Crear communication price', tag: 'Communication Prices', responseDto: CommunicationPriceOutDto::class, responseStatusCode: 201)]
    public function create(Request $request): JsonResponse
    {
        $data   = $request->toArray();
        $errors = [];
        if (!isset($data['startPrice'])) $errors[] = 'startPrice is required';
        if (!isset($data['amount']))     $errors[] = 'amount is required';
        if (!empty($errors)) {
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $errors]], Response::HTTP_BAD_REQUEST);
        }

        $cp = new CommunicationPrice();
        $cp->setStartPrice((float) $data['startPrice']);
        $cp->setEndPrice(isset($data['endPrice']) ? (float) $data['endPrice'] : null);
        $cp->setCurrencyPrice(strtoupper($data['currencyPrice'] ?? 'CUP'));
        $cp->setAmount((float) $data['amount']);
        $cp->setCurrency(strtoupper($data['currency'] ?? 'USD'));
        $cp->setIsActive($data['isActive'] ?? true);
        $cp->setValidStartAt(new \DateTimeImmutable($data['validStartAt'] ?? 'now'));
        if (!empty($data['validEndAt'])) {
            $cp->setValidEndAt(new \DateTimeImmutable($data['validEndAt']));
        }

        $this->em->persist($cp);
        $this->em->flush();

        return $this->json($this->serialize($cp), Response::HTTP_CREATED);
    }

    #[Route('/client/communication-prices/{id}', name: 'dashboard_communication_prices_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar communication price', tag: 'Communication Prices', responseDto: CommunicationPriceOutDto::class)]
    public function update(int $id, Request $request): JsonResponse
    {
        $cp = $this->em->getRepository(CommunicationPrice::class)->find($id);
        if ($cp === null) {
            return $this->json(['error' => ['message' => 'Not found']], Response::HTTP_NOT_FOUND);
        }

        $data = $request->toArray();
        if (isset($data['startPrice']))   $cp->setStartPrice((float) $data['startPrice']);
        if (array_key_exists('endPrice', $data)) $cp->setEndPrice($data['endPrice'] !== null ? (float) $data['endPrice'] : null);
        if (isset($data['currencyPrice'])) $cp->setCurrencyPrice(strtoupper($data['currencyPrice']));
        if (isset($data['amount']))        $cp->setAmount((float) $data['amount']);
        if (isset($data['currency']))      $cp->setCurrency(strtoupper($data['currency']));
        if (isset($data['isActive']))      $cp->setIsActive((bool) $data['isActive']);
        if (isset($data['validStartAt']))  $cp->setValidStartAt(new \DateTimeImmutable($data['validStartAt']));
        if (array_key_exists('validEndAt', $data)) {
            $cp->setValidEndAt($data['validEndAt'] !== null ? new \DateTimeImmutable($data['validEndAt']) : null);
        }

        $this->em->flush();
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
        $cp->setIsActive(!$cp->isActive());
        $this->em->flush();
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
        $this->em->remove($cp);
        $this->em->flush();
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
