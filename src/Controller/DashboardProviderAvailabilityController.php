<?php

namespace App\Controller;

use App\DTO\SetProviderAvailabilityDto;
use App\Enums\CommunicationProviderEnum;
use App\Exception\MyCurrentException;
use App\OpenApi\Attribute\DashboardEndpoint;
use App\Service\CommunicationsDispatchService;
use App\Service\Provider\ProviderAvailabilityService;
use App\Service\Provider\ProviderPingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Ping periódico de proveedores y el gate que habilita/deshabilita el
 * despacho de transacciones. No sustituye a DashboardProviderRoutingController
 * (que administra credenciales y enrutado por cliente) — este controlador es
 * solo el estado de disponibilidad: la matriz proveedor x entorno, el toggle
 * manual auditado, y el ping forzado.
 *
 * Lectura: ROLE_ADMIN. Escritura (toggle manual): ROLE_SUPER_ADMIN, mismo
 * criterio que el resto de endpoints que afectan el envío de transacciones.
 */
#[Route('/admin/providers/availability')]
#[IsGranted('ROLE_ADMIN')]
class DashboardProviderAvailabilityController extends AbstractController
{
    public function __construct(
        private readonly ProviderAvailabilityService $availabilityService,
        private readonly ProviderPingService $pingService,
        private readonly CommunicationsDispatchService $dispatchService,
    ) {
    }

    #[Route('', name: 'dashboard_provider_availability_matrix', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Matriz de disponibilidad proveedor x entorno', tag: 'Provider Availability', responseIsArray: true)]
    public function matrix(): JsonResponse
    {
        return $this->json($this->availabilityService->statusMatrix());
    }

    #[Route('/{code}/{environmentType}/manual', name: 'dashboard_provider_availability_set_manual', methods: ['PATCH'], requirements: ['code' => 'ETECSA|DTONE|CSQ', 'environmentType' => 'TEST|PROD'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[DashboardEndpoint(summary: 'Activar o desactivar manualmente el despacho hacia un proveedor, auditado', tag: 'Provider Availability')]
    public function setManual(string $code, string $environmentType, SetProviderAvailabilityDto $dto): JsonResponse
    {
        $provider = CommunicationProviderEnum::tryFrom($code);
        if ($provider === null) {
            return $this->json(['error' => ['message' => 'Proveedor no encontrado']], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->availabilityService->setManual($provider, $environmentType, $dto->getActive() ?? true, $dto->getReason());
        } catch (MyCurrentException $e) {
            return $this->json(['error' => ['message' => $e->getMessage()]], $e->getCode());
        }

        return $this->json($this->availabilityService->statusMatrix());
    }

    #[Route('/{code}/{environmentType}/ping', name: 'dashboard_provider_availability_force_ping', methods: ['POST'], requirements: ['code' => 'ETECSA|DTONE|CSQ', 'environmentType' => 'TEST|PROD'])]
    #[DashboardEndpoint(summary: 'Forzar un ping inmediato y registrar el resultado', tag: 'Provider Availability')]
    public function forcePing(string $code, string $environmentType): JsonResponse
    {
        $provider = CommunicationProviderEnum::tryFrom($code);
        if ($provider === null) {
            return $this->json(['error' => ['message' => 'Proveedor no encontrado']], Response::HTTP_NOT_FOUND);
        }

        $result = $this->pingService->ping($provider, $environmentType);
        $justRecovered = $this->availabilityService->recordPing($provider, $environmentType, $result);

        if ($justRecovered) {
            $this->dispatchService->redispatchPendingFor($provider->value, $environmentType);
        }

        return $this->json($this->availabilityService->statusMatrix());
    }
}
