<?php

namespace App\Controller;

use App\DTO\Out\AccountSecOutDto;
use App\DTO\Out\ClientSecOutDto;
use App\DTO\Out\DeletedOutDto;
use App\DTO\Out\RegenerateTokenOutDto;
use App\DTO\Out\ToggleOutDto;
use App\DTO\UpdateAccountSecurityDto;
use App\Entity\Account;
use App\Entity\Client;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\AccountSecurityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/clients/sec')]
class DashboardClientSecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly AccountSecurityService $accountSecurityService,
    ) {
    }

    #[Route('', name: 'dashboard_client_sec_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar clientes con cuentas', tag: 'Client Security', responseDto: ClientSecOutDto::class, responseIsArray: true)]
    public function list(): JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $clients = $this->em->getRepository(Client::class)->findBy(
                ['isActive' => true],
                ['companyName' => 'ASC']
            );
        } else {
            $client = $user->getCompany();
            $clients = ($client !== null && $client->isActive()) ? [$client] : [];
        }

        return $this->json(array_map(fn(Client $c) => $this->serializeClient($c), $clients));
    }

    #[Route('/{clientId}', name: 'dashboard_client_sec_show', methods: ['GET'], requirements: ['clientId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Obtener cliente con cuentas', tag: 'Client Security', responseDto: ClientSecOutDto::class)]
    public function show(int $clientId): JsonResponse
    {
        $client = $this->findClientWithAccess($clientId);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        return $this->json($this->serializeClient($client));
    }

    #[Route('/{clientId}/accounts/{accountId}', name: 'dashboard_client_sec_update_account', methods: ['PUT'], requirements: ['clientId' => '\d+', 'accountId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar cuenta', tag: 'Client Security', requestDto: UpdateAccountSecurityDto::class, responseDto: AccountSecOutDto::class)]
    public function updateAccount(int $clientId, int $accountId, UpdateAccountSecurityDto $dto): JsonResponse
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $v) {
                $details[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            return $this->json(['error' => ['message' => 'Validation failed', 'details' => $details]], Response::HTTP_BAD_REQUEST);
        }

        $client = $this->findClientWithAccess($clientId);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $account = $this->em->getRepository(Account::class)->find($accountId);
        if ($account === null || $account->getClient()?->getId() !== $clientId) {
            return $this->json(['error' => ['message' => 'Account not found for this client']], Response::HTTP_NOT_FOUND);
        }

        try {
            $account = $this->accountSecurityService->update($account, $dto, $this->isGranted('ROLE_ADMIN'));
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serializeAccount($account));
    }

    #[Route('/{clientId}/accounts/{accountId}/regenerate-token', name: 'dashboard_client_sec_regen_token', methods: ['POST'], requirements: ['clientId' => '\d+', 'accountId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Regenerar token de acceso', tag: 'Client Security', responseDto: RegenerateTokenOutDto::class, responseStatusCode: 201)]
    public function regenerateToken(int $clientId, int $accountId): JsonResponse
    {
        $client = $this->findClientWithAccess($clientId);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $account = $this->em->getRepository(Account::class)->find($accountId);
        if ($account === null || $account->getClient()?->getId() !== $clientId) {
            return $this->json(['error' => ['message' => 'Account not found for this client']], Response::HTTP_NOT_FOUND);
        }

        $account = $this->accountSecurityService->regenerateToken($account);

        return $this->json([
            'id' => $account->getId(),
            'accessToken' => $account->getAccessToken()->toRfc4122(),
            'message' => 'Token regenerated. Update your API integration with the new token.',
        ]);
    }

    #[Route('/{clientId}/accounts/{accountId}/toggle', name: 'dashboard_client_sec_toggle', methods: ['PATCH'], requirements: ['clientId' => '\d+', 'accountId' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar/desactivar cuenta', tag: 'Client Security', responseDto: ToggleOutDto::class)]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleAccount(int $clientId, int $accountId): JsonResponse
    {
        $client = $this->findClientWithAccess($clientId);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $account = $this->em->getRepository(Account::class)->find($accountId);
        if ($account === null || $account->getClient()?->getId() !== $clientId) {
            return $this->json(['error' => ['message' => 'Account not found for this client']], Response::HTTP_NOT_FOUND);
        }

        $account = $this->accountSecurityService->toggle($account);

        return $this->json([
            'id' => $account->getId(),
            'isActive' => $account->isActive(),
        ]);
    }

    // ─── Helpers ────────────────────────────────

    private function getAuthUser(): ?User
    {
        $user = $this->getUser();
        return $user instanceof User ? $user : null;
    }

    private function findClientWithAccess(int $clientId): Client|JsonResponse
    {
        $user = $this->getAuthUser();
        if ($user === null) {
            return $this->json(['error' => ['message' => 'Unauthorized']], Response::HTTP_UNAUTHORIZED);
        }

        $client = $this->em->getRepository(Client::class)->find($clientId);
        if ($client === null) {
            return $this->json(['error' => ['message' => 'Client not found']], Response::HTTP_NOT_FOUND);
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $client;
        }

        if ($this->isGranted('ROLE_API_ADMIN') && $user->getCompany()?->getId() === $clientId) {
            return $client;
        }

        return $this->json(['error' => ['message' => 'Access denied']], Response::HTTP_FORBIDDEN);
    }

    private function serializeClient(Client $client): array
    {
        $accounts = $this->em->getRepository(Account::class)->findBy(
            ['client' => $client],
            ['environment' => 'ASC']
        );

        return [
            'id' => $client->getId(),
            'companyName' => $client->getCompanyName(),
            'companyCountry' => $client->getCompanyCountry(),
            'companyIdentification' => $client->getCompanyIdentification(),
            'companyEmail' => $client->getCompanyEmail(),
            'isActive' => $client->isActive(),
            'accounts' => array_map(fn(Account $a) => $this->serializeAccount($a), $accounts),
        ];
    }

    private function serializeAccount(Account $account): array
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $data = [
            'id' => $account->getId(),
            'accessToken' => $account->getAccessToken()?->toRfc4122(),
            'origin' => $account->getOrigin(),
            'contractCurrency' => $account->getContractCurrency(),
            'minBalance' => $account->getMinBalance(),
            'criticalBalance' => $account->getCriticalBalance(),
            'environment' => [
                'id' => $account->getEnvironment()?->getId(),
                'type' => $account->getEnvironment()?->getType(),
            ],
        ];

        if ($isAdmin) {
            $data['isActive'] = $account->isActive();
            $data['discount'] = $account->getDiscount();
            $data['commission'] = $account->getCommission();
        }

        return $data;
    }
}
