<?php

declare(strict_types=1);

namespace App\Market\Catalogo\Infrastructure\Http;

use App\Market\Catalogo\Application\ChangeMeasureSettings;
use App\Market\Catalogo\Application\GetMeasureSettings;
use App\Market\Catalogo\Domain\InvalidMeasureSettings;
use App\Market\Catalogo\Domain\MeasureSettingsLocked;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Las medidas del catálogo (COMR-281): unidades base de peso y volumen y divisor del peso volumétrico. El controlador solo
 * traduce HTTP; el cambio de base se bloquea en el caso de uso si ya hay productos con cantidades.
 */
#[AsController]
#[Route('/market/measure-settings')]
#[IsGranted('ROLE_API_ADMIN')]
final class MeasureSettingsController
{
    public function __construct(
        private readonly GetMeasureSettings $get,
        private readonly ChangeMeasureSettings $change,
    ) {
    }

    #[Route('', name: 'market_measure_settings_get', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Las unidades base de peso y volumen, el divisor volumétrico y si la base está bloqueada', tag: 'Market', responseDto: MeasureSettingsOutDto::class)]
    public function get(): JsonResponse
    {
        return new JsonResponse(MeasureSettingsOutDto::from(($this->get)()));
    }

    #[Route('', name: 'market_measure_settings_change', methods: ['PUT'])]
    #[DashboardEndpoint(summary: 'Cambiar las unidades base y el divisor (la base se rechaza con 409 si ya hay productos con cantidades)', tag: 'Market', requestDto: ChangeMeasureSettingsDto::class, responseDto: MeasureSettingsOutDto::class)]
    public function change(ChangeMeasureSettingsDto $dto): JsonResponse
    {
        try {
            return new JsonResponse(MeasureSettingsOutDto::from(($this->change)((string) $dto->getWeightBase(), (string) $dto->getVolumeBase(), (int) $dto->getVolumetricDivisor())));
        } catch (InvalidMeasureSettings $e) {
            return $this->error('INVALID_MEASURE_SETTINGS', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (MeasureSettingsLocked $e) {
            return $this->error('MEASURE_SETTINGS_LOCKED', $e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
