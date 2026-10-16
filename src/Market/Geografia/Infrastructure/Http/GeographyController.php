<?php

declare(strict_types=1);

namespace App\Market\Geografia\Infrastructure\Http;

use App\Market\Geografia\Application\ListProvinces;
use App\Market\Geografia\Application\MunicipalityDetails;
use App\Market\Geografia\Application\ProvinceDetails;
use App\Market\Geografia\Application\SearchMunicipalities;
use App\Market\Geografia\Application\SetMunicipalityActive;
use App\Market\Geografia\Application\SetProvinceActive;
use App\Market\Geografia\Domain\GeographyNotFound;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Provincias y municipios de Cuba (COMR-286). El controlador solo traduce HTTP.
 */
#[AsController]
#[Route('/market/geography')]
#[IsGranted('ROLE_API_ADMIN')]
final class GeographyController
{
    public function __construct(
        private readonly ListProvinces $listProvinces,
        private readonly SearchMunicipalities $searchMunicipalities,
        private readonly SetProvinceActive $setProvinceActive,
        private readonly SetMunicipalityActive $setMunicipalityActive,
    ) {
    }

    #[Route('/provinces', name: 'market_geography_provinces', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar las provincias con su número de municipios', tag: 'Market', responseDto: ProvinceOutDto::class, responseIsArray: true)]
    public function provinces(): JsonResponse
    {
        return new JsonResponse(array_map(static fn (ProvinceDetails $d) => ProvinceOutDto::from($d), ($this->listProvinces)()));
    }

    #[Route('/municipalities', name: 'market_geography_municipalities', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Buscar municipios (filtros: q sin acentos, province, selectable)', tag: 'Market', responseDto: MunicipalityOutDto::class, responseIsArray: true)]
    public function municipalities(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        $province = $request->query->get('province');
        $selectable = match ($request->query->get('selectable')) {
            'true', '1' => true,
            'false', '0' => false,
            default => null,
        };

        return new JsonResponse(array_map(
            static fn (MunicipalityDetails $d) => MunicipalityOutDto::from($d),
            ($this->searchMunicipalities)(is_string($query) ? $query : null, is_string($province) && ctype_digit($province) ? (int) $province : null, $selectable),
        ));
    }

    #[Route('/provinces/{id}/active', name: 'market_geography_province_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar o desactivar una provincia (oculta sus municipios sin cambiarlos)', tag: 'Market', requestDto: SetActiveDto::class, responseDto: ProvinceOutDto::class)]
    public function setProvinceActive(int $id, SetActiveDto $dto): JsonResponse
    {
        try {
            return new JsonResponse(ProvinceOutDto::from(($this->setProvinceActive)($id, (bool) $dto->getActive())));
        } catch (GeographyNotFound $e) {
            return $this->notFound($e);
        }
    }

    #[Route('/municipalities/{id}/active', name: 'market_geography_municipality_active', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[DashboardEndpoint(summary: 'Activar o desactivar un municipio', tag: 'Market', requestDto: SetActiveDto::class, responseDto: MunicipalityOutDto::class)]
    public function setMunicipalityActive(int $id, SetActiveDto $dto): JsonResponse
    {
        try {
            return new JsonResponse(MunicipalityOutDto::from(($this->setMunicipalityActive)($id, (bool) $dto->getActive())));
        } catch (GeographyNotFound $e) {
            return $this->notFound($e);
        }
    }

    private function notFound(GeographyNotFound $e): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $e->getMessage(), 'code' => 'GEOGRAPHY_NOT_FOUND']], Response::HTTP_NOT_FOUND);
    }
}
