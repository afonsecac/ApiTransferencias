<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BalanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[Route("/admin/dashboard/financial")]
#[IsGranted("ROLE_SYSTEM_SHOW")]
class AdminDashboardFinancialController extends AbstractController
{
    public function __construct(
        private readonly BalanceService $balanceService,
        private readonly SerializerInterface $serializer
    ) {

    }

    #[Route("/recent-transactions", name: "admin_dashboard_financial_recent_txs", methods: ["GET"])]
    public function recentTransactions(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 5);

        return $this->json(
            $this->serializer->normalize(
                $this->balanceService->recentTransactions($limit),
                'json',
                [
                    'groups' => ['balance:reading']
                ]
            )
        );
    }
}
