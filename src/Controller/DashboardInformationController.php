<?php

declare(strict_types=1);

namespace App\Controller;

use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\DashboardStatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/information')]
#[IsGranted('ROLE_SYSTEM_SHOW')]
class DashboardInformationController extends AbstractController
{
    public function __construct(
        private readonly DashboardStatisticsService $statsService,
    ) {}

    private function extractFilters(Request $request): array
    {
        return [
            'dateFrom'        => $request->query->get('dateFrom'),
            'dateTo'          => $request->query->get('dateTo'),
            'clientId'        => $request->query->get('clientId')
                ? (int) $request->query->get('clientId') : null,
            'environmentType' => $request->query->get('environmentType'),
            'type'            => $request->query->get('type'),
        ];
    }

    #[Route('/summary', name: 'dashboard_info_summary', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Resumen de operaciones', tag: 'Information')]
    public function summary(Request $request): JsonResponse
    {
        return $this->json(
            $this->statsService->getSummary($this->extractFilters($request))
        );
    }

    #[Route('/operations-by-client', name: 'dashboard_info_ops_by_client', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Operaciones agrupadas por cliente', tag: 'Information')]
    public function operationsByClient(Request $request): JsonResponse
    {
        return $this->json(
            $this->statsService->getOperationsByClient($this->extractFilters($request))
        );
    }

    #[Route('/operations-over-time', name: 'dashboard_info_ops_over_time', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Operaciones a lo largo del tiempo', tag: 'Information')]
    public function operationsOverTime(Request $request): JsonResponse
    {
        $filters = $this->extractFilters($request);
        $groupBy = $request->query->get('groupBy', 'day');
        return $this->json(
            $this->statsService->getOperationsOverTime($filters, $groupBy)
        );
    }

    #[Route('/busiest-days', name: 'dashboard_info_busiest_days', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Días con más actividad', tag: 'Information')]
    public function busiestDays(Request $request): JsonResponse
    {
        return $this->json(
            $this->statsService->getBusiestDays($this->extractFilters($request))
        );
    }

    #[Route('/peak-hours', name: 'dashboard_info_peak_hours', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Horas pico de operaciones', tag: 'Information')]
    public function peakHours(Request $request): JsonResponse
    {
        return $this->json(
            $this->statsService->getPeakHours($this->extractFilters($request))
        );
    }

    #[Route('/top-packages', name: 'dashboard_info_top_packages', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Paquetes más vendidos', tag: 'Information')]
    public function topPackages(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        return $this->json(
            $this->statsService->getTopPackages($this->extractFilters($request), $limit)
        );
    }
}
