<?php

namespace App\Controller;

use App\DTO\Out\ClientOutDto;
use App\DTO\Out\CompanyRefOutDto;
use App\DTO\Out\PaginatedListOutDto;
use App\DTO\UpdateClientDto;
use App\Entity\Client;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\ClientService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/client')]
class AdminClientController extends AbstractController
{
    public function __construct(
        private readonly ClientService $clientService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/all', name: 'admin_client_all', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar todos los clientes', tag: 'Admin Clients', responseDto: PaginatedListOutDto::class, itemDto: CompanyRefOutDto::class)]
    public function index(
        #[MapQueryParameter] int    $page = 0,
        #[MapQueryParameter] int    $limit = 10,
        #[MapQueryParameter] string $orderBy = 'companyName',
        #[MapQueryParameter] string $direction = 'ASC',
    ): JsonResponse {
        return $this->json($this->clientService->getAllClients([
            'page'      => $page,
            'limit'     => $limit,
            'orderBy'   => $orderBy,
            'direction' => $direction,
        ]));
    }

    #[Route('/{id}', name: 'admin_client_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Actualizar cliente', tag: 'Admin Clients', requestDto: UpdateClientDto::class, responseDto: ClientOutDto::class)]
    public function update(int $id, UpdateClientDto $dto): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->json(['error' => ['message' => 'Access denied']], Response::HTTP_FORBIDDEN);
        }

        $client = $this->em->getRepository(Client::class)->find($id);
        if ($client === null) {
            return $this->json(['error' => ['message' => 'Client not found']], Response::HTTP_NOT_FOUND);
        }

        try {
            $client = $this->clientService->update($client, $dto);
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->serializeClient($client));
    }

    private function serializeClient(Client $client): array
    {
        return [
            'id'                      => $client->getId(),
            'companyName'             => $client->getCompanyName(),
            'companyAddress'          => $client->getCompanyAddress(),
            'companyCountry'          => $client->getCompanyCountry(),
            'companyZipCode'          => $client->getCompanyZipCode(),
            'companyEmail'            => $client->getCompanyEmail(),
            'companyPhoneNumber'      => $client->getCompanyPhoneNumber(),
            'discountOfClient'        => $client->getDiscountOfClient(),
            'companyIdentification'   => $client->getCompanyIdentification(),
            'companyIdentificationType' => $client->getCompanyIdentificationType(),
            'minBalance'              => $client->getMinBalance(),
            'criticalBalance'         => $client->getCriticalBalance(),
            'currency'                => $client->getCurrency(),
            'isAlert'                 => $client->isAlert(),
            'contractWith'            => $client->getContractWith(),
            'isActive'                => $client->isActive(),
            'createdAt'               => $client->getCreatedAt()?->format('c'),
            'updatedAt'               => $client->getUpdatedAt()?->format('c'),
        ];
    }
}
