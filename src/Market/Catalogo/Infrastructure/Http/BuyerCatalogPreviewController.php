<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\OpenApi\Attribute\DashboardEndpoint;
use App\Repository\AccountRepository;
use App\Service\Catalog\MarketPackageEnricher;
use App\State\CommunicationPackageCatalogProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vista previa del catálogo del comprador (COMR-275, propuesta P2): lo que recibe una cuenta, sin llamar a la API a mano. Aplica lo
 * mismo que el catálogo real: primero el routing de proveedores de la cuenta y, sobre eso, el filtro de servicio, subservicio
 * y área. El orden es el del catálogo (por id) y los datos de mercado se leen solo de los paquetes de la página devuelta.
 */
#[AsController]
#[Route('/market/buyer-catalog')]
#[IsGranted('ROLE_API_ADMIN')]
final class BuyerCatalogPreviewController
{
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly CommunicationPackageCatalogProvider $catalog,
        private readonly MarketPackageEnricher $enricher,
    ) {
    }

    #[Route('', name: 'market_buyer_catalog_preview', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Vista previa de lo que recibe una cuenta en el catálogo del comprador (filtros: account, service, subservice, area, page, perPage)', tag: 'Market', responseDto: BuyerCatalogPageOutDto::class)]
    public function preview(Request $request): JsonResponse
    {
        $accountId = $request->query->get('account');
        if (!is_string($accountId) || !ctype_digit($accountId) || (int) $accountId < 1) {
            return $this->error('ACCOUNT_REQUIRED', 'Indica la cuenta (account).', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $account = $this->accounts->find((int) $accountId);
        if ($account === null) {
            return $this->error('ACCOUNT_NOT_FOUND', sprintf('No existe la cuenta %d.', (int) $accountId), Response::HTTP_NOT_FOUND);
        }

        $now = new \DateTimeImmutable();
        $visible = $this->catalog->filterByCategoryOf(
            $this->catalog->visibleFor($account, $now),
            $request->query->get('service'),
            $request->query->get('subservice'),
            $request->query->get('area'),
        );
        usort($visible, static fn ($a, $b): int => ($a->getId() ?? 0) <=> ($b->getId() ?? 0));

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) $request->query->get('perPage', 25)));
        $slice = array_slice($visible, ($page - 1) * $perPage, $perPage);
        $this->enricher->enrich($slice, $now);

        return new JsonResponse([
            'items' => array_map(static fn ($package): array => BuyerCatalogItemOutDto::from($package)->toArray(), $slice),
            'total' => count($visible),
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
