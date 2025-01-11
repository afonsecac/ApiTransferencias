<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\DTO\BudgetInfoDto;
use App\Entity\User;
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

    #[Route("", name: "admin_dashboard_financial", methods: ["GET"])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated');
        }
        $balances = $this->balanceService->getBalancesByEnvironment($user?->getCompany()?->getId());
        $limit = $request->query->getInt('limit', 5);
        $txRecent = $this->serializer->serialize(
            $this->balanceService->recentTransactions($limit),
            'json',
            ['groups' => ['balance:reading']]
        );
        return $this->json([
            'recentTransactions' => $txRecent,
            'balances' => $balances,
            'accountBalance' => [
                'growRate' => 0,
                'ami' => 0,
                'series' => [
                    [
                        'name' => 'Predicted',
                        'data' => []
                    ],
                    [
                        'name' => 'Actual',
                        'data' => []
                    ]
                 ]
            ],
            'budget' => [
                'expenses' => new BudgetInfoDto(0, 0, 0, 0),
                'savings' => new BudgetInfoDto(0, 0, 0, 0),
                'bills' => new BudgetInfoDto(0, 0, 0, 0),
            ]
        ]);
    }
}
