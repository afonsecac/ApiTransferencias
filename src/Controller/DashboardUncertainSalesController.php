<?php

namespace App\Controller;

use App\DTO\Out\PaginatedListOutDto;
use App\DTO\Out\UncertainSaleActionOutDto;
use App\DTO\Out\UncertainSaleOutDto;
use App\DTO\ResolveUncertainSaleDto;
use App\Entity\CommunicationSaleInfo;
use App\Entity\CommunicationSaleRecharge;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\UncertainSaleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ventas «que requieren revisión» (COMR-258): las inciertas de COMR-257. Su importe sigue comprometido
 * hasta que se resuelven aquí, con usuario y motivo, o las confirma el proveedor.
 */
#[Route('/packages/sales/uncertain')]
#[IsGranted('ROLE_API_ADMIN')]
class DashboardUncertainSalesController extends AbstractController
{
    public function __construct(
        private readonly UncertainSaleService $uncertainSaleService,
    ) {
    }

    #[Route('', name: 'dashboard_uncertain_sales_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar ventas que requieren revisión', tag: 'Sales', responseDto: PaginatedListOutDto::class, itemDto: UncertainSaleOutDto::class)]
    public function list(Request $request): JsonResponse
    {
        $page = max(0, (int) $request->query->get('page', 0));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $result = $this->uncertainSaleService->list($page, $limit);

        return $this->json([
            'limit' => $limit,
            'currentPage' => $page,
            'hasNext' => $result['hasNext'],
            'hasPrevious' => $page > 0,
            'results' => array_map($this->present(...), $result['items']),
        ]);
    }

    #[Route('/{id}/recheck', name: 'dashboard_uncertain_sales_recheck', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Consultar de nuevo una venta incierta', tag: 'Sales', responseStatusCode: 202)]
    public function recheck(int $id): JsonResponse
    {
        try {
            $this->uncertainSaleService->recheck($id);
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        return $this->json(['id' => $id, 'queued' => true], Response::HTTP_ACCEPTED);
    }

    #[Route('/{id}/resolve', name: 'dashboard_uncertain_sales_resolve', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Resolver a mano una venta incierta (completada o rechazada)', tag: 'Sales', requestDto: ResolveUncertainSaleDto::class, responseDto: UncertainSaleActionOutDto::class)]
    public function resolve(int $id, ResolveUncertainSaleDto $dto): JsonResponse
    {
        $user = $this->getUser();

        try {
            $sale = $this->uncertainSaleService->resolve(
                $id,
                (string) $dto->getResolution(),
                (string) $dto->getReason(),
                $user instanceof User ? $user->getEmail() : null,
            );
        } catch (MyCurrentException $e) {
            return $this->errorResponse($e);
        }

        return $this->json([
            'id' => $sale->getId(),
            'state' => $sale->getState()?->value,
            'stateProcess' => $sale->getStateProcess(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CommunicationSaleInfo $sale): array
    {
        $status = $sale->getTransactionStatus();
        $createdAt = $sale->getCreatedAt();

        return [
            'id' => $sale->getId(),
            'transactionId' => $sale->getTransactionId(),
            'type' => $sale instanceof CommunicationSaleRecharge ? 'recharge' : 'package',
            'provider' => $sale->getProvider(),
            'accountId' => $sale->getTenant()?->getId(),
            'reservedAmount' => (float) $sale->getTotalPrice(),
            'currency' => $sale->getCurrency(),
            'createdAt' => $createdAt?->format(\DateTimeInterface::ATOM),
            'ageMinutes' => $createdAt !== null ? max(0, intdiv(time() - $createdAt->getTimestamp(), 60)) : 0,
            'checkAttempts' => $sale->getCheckAttempts(),
            'nextCheckAt' => $sale->getNextCheckAt()?->format(\DateTimeInterface::ATOM),
            'lastStatus' => [
                'outcome' => $status['outcome'] ?? null,
                'code' => $status['providerCode'] ?? null,
                'message' => $status['message'] ?? null,
            ],
            'reason' => $status['retry']['reason'] ?? $status['message'] ?? null,
        ];
    }

    private function errorResponse(MyCurrentException $e): JsonResponse
    {
        return $this->json(
            ['error' => ['message' => $e->getMessage(), 'code' => $e->getCodeWork()]],
            $e->getCode() ?: Response::HTTP_BAD_REQUEST
        );
    }
}
