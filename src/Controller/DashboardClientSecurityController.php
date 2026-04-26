<?php

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/clients/sec')]
class DashboardClientSecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Lista clientes con sus cuentas y datos de seguridad.
     * ROLE_ADMIN/SUPER_ADMIN: todos los clientes.
     * ROLE_API_ADMIN: solo su cliente.
     */
    #[Route('', name: 'dashboard_client_sec_list', methods: ['GET'])]
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

        $result = array_map(fn(Client $c) => $this->serializeClient($c), $clients);

        return $this->json($result);
    }

    /**
     * Detalle de un cliente con sus cuentas.
     */
    #[Route('/{clientId}', name: 'dashboard_client_sec_show', methods: ['GET'], requirements: ['clientId' => '\d+'])]
    public function show(int $clientId): JsonResponse
    {
        $client = $this->findClientWithAccess($clientId);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        return $this->json($this->serializeClient($client));
    }

    /**
     * Actualizar datos de seguridad de una cuenta (origin IPs, active, balances).
     */
    #[Route('/{clientId}/accounts/{accountId}', name: 'dashboard_client_sec_update_account', methods: ['PUT'], requirements: ['clientId' => '\d+', 'accountId' => '\d+'])]
    public function updateAccount(int $clientId, int $accountId, Request $request): JsonResponse
    {
        $client = $this->findClientWithAccess($clientId);
        if ($client instanceof JsonResponse) {
            return $client;
        }

        $account = $this->em->getRepository(Account::class)->find($accountId);
        if ($account === null || $account->getClient()?->getId() !== $clientId) {
            return $this->json(['error' => ['message' => 'Account not found for this client']], Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();

        if (isset($data['origin'])) {
            $account->setOrigin($data['origin']);
        }
        if (isset($data['minBalance'])) {
            $account->setMinBalance((float) $data['minBalance']);
        }
        if (isset($data['criticalBalance'])) {
            $account->setCriticalBalance((float) $data['criticalBalance']);
        }
        // Solo ROLE_ADMIN puede modificar isActive, discount y commission
        if ($this->isGranted('ROLE_ADMIN')) {
            if (isset($data['isActive'])) {
                $account->setIsActive((bool) $data['isActive']);
                $account->setIsActiveAt(new \DateTimeImmutable('now'));
            }
            if (isset($data['discount'])) {
                $account->setDiscount((float) $data['discount']);
            }
            if (isset($data['commission'])) {
                $account->setCommission((float) $data['commission']);
            }
        }

        $this->em->flush();

        return $this->json($this->serializeAccount($account));
    }

    /**
     * Regenerar el access token de una cuenta.
     */
    #[Route('/{clientId}/accounts/{accountId}/regenerate-token', name: 'dashboard_client_sec_regen_token', methods: ['POST'], requirements: ['clientId' => '\d+', 'accountId' => '\d+'])]
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

        $account->setAccessToken(Uuid::v4());
        $this->em->flush();

        return $this->json([
            'id' => $account->getId(),
            'accessToken' => $account->getAccessToken()->toRfc4122(),
            'message' => 'Token regenerated. Update your API integration with the new token.',
        ]);
    }

    /**
     * Activar/desactivar una cuenta. Solo ROLE_ADMIN.
     */
    #[Route('/{clientId}/accounts/{accountId}/toggle', name: 'dashboard_client_sec_toggle', methods: ['PATCH'], requirements: ['clientId' => '\d+', 'accountId' => '\d+'])]
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

        $account->setIsActive(!$account->isActive());
        $account->setIsActiveAt(new \DateTimeImmutable('now'));
        $this->em->flush();

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

    /**
     * Verifica que el usuario tenga acceso al cliente.
     * Retorna el Client o un JsonResponse de error.
     */
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

        // ROLE_ADMIN y ROLE_SUPER_ADMIN: acceso a todo
        if ($this->isGranted('ROLE_ADMIN')) {
            return $client;
        }

        // ROLE_API_ADMIN: solo su propio cliente
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

        // Solo ROLE_ADMIN ve isActive, discount y commission
        if ($isAdmin) {
            $data['isActive'] = $account->isActive();
            $data['discount'] = $account->getDiscount();
            $data['commission'] = $account->getCommission();
        }

        return $data;
    }
}
