<?php

namespace App\Controller;

use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/admin/report')]
class AdminReportController extends AbstractController
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly NormalizerInterface $serializer
    ) {

    }

    #[Route(name: 'admin_report', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar reportes', tag: 'Admin Reports', responseIsArray: true)]
    public function __invoke(
        #[MapQueryParameter] int $page = 0,
        #[MapQueryParameter] int $limit = 40,
    ): JsonResponse {
        return $this->json(
            $this->serializer->normalize(
                $this->reportService->getAllReports($page, $limit),
                'json',
                [
                    'groups' => [
                        'reports:list',
                    ],
                ]
            )
        );
    }

    #[Route('/{id}', name: 'admin_report_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Obtener reporte', tag: 'Admin Reports')]
    public function getReport(int $id): JsonResponse
    {
        return $this->json(
            $this->serializer->normalize(
                $this->reportService->getReport($id),
                'json',
                [
                    'groups' => [
                        'report:read',
                    ],
                ]
            )
        );
    }
}