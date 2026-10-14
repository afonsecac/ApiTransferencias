<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Infrastructure\Http;

use App\Market\Organizacion\Application\CreateFiscalProfile;
use App\Market\Organizacion\Application\FiscalFieldInput;
use App\Market\Organizacion\Application\GetFiscalProfile;
use App\Market\Organizacion\Application\ListFiscalProfiles;
use App\Market\Organizacion\Application\UpdateFiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfile;
use App\Market\Organizacion\Domain\FiscalProfileAlreadyExists;
use App\Market\Organizacion\Domain\FiscalProfileNotFound;
use App\Market\Organizacion\Domain\InvalidFiscalProfile;
use App\OpenApi\Attribute\DashboardEndpoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Registros fiscales por país (COMR-270): qué datos fiscales se piden para facturar y con qué formato.
 * El controlador solo traduce HTTP: el DTO ya viene validado por el resolver, la lógica está en los
 * casos de uso y las excepciones de dominio se convierten en respuestas aquí.
 */
#[AsController]
#[Route('/market/fiscal-profiles')]
#[IsGranted('ROLE_API_ADMIN')]
final class FiscalProfileController
{
    public function __construct(
        private readonly ListFiscalProfiles $listProfiles,
        private readonly GetFiscalProfile $getProfile,
        private readonly CreateFiscalProfile $createProfile,
        private readonly UpdateFiscalProfile $updateProfile,
    ) {
    }

    #[Route('', name: 'market_fiscal_profiles_list', methods: ['GET'])]
    #[DashboardEndpoint(summary: 'Listar los registros fiscales por país', tag: 'Market', responseDto: FiscalProfileOutDto::class, responseIsArray: true)]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn (FiscalProfile $p) => FiscalProfileOutDto::from($p),
            ($this->listProfiles)(),
        ));
    }

    #[Route('/{countryCode}', name: 'market_fiscal_profiles_get', methods: ['GET'], requirements: ['countryCode' => '[A-Z]{2}'])]
    #[DashboardEndpoint(summary: 'Obtener el registro fiscal de un país', tag: 'Market', responseDto: FiscalProfileOutDto::class)]
    public function get(string $countryCode): JsonResponse
    {
        try {
            return new JsonResponse(FiscalProfileOutDto::from(($this->getProfile)($countryCode)));
        } catch (FiscalProfileNotFound $e) {
            return $this->error('FISCAL_PROFILE_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('', name: 'market_fiscal_profiles_create', methods: ['POST'])]
    #[DashboardEndpoint(summary: 'Crear el registro fiscal de un país', tag: 'Market', requestDto: CreateFiscalProfileDto::class, responseDto: FiscalProfileOutDto::class, responseStatusCode: 201)]
    public function create(CreateFiscalProfileDto $dto): JsonResponse
    {
        try {
            $profile = ($this->createProfile)((string) $dto->getCountryCode(), (string) $dto->getName(), $this->inputs($dto->getFields()));
        } catch (FiscalProfileAlreadyExists $e) {
            return $this->error('FISCAL_PROFILE_ALREADY_EXISTS', $e->getMessage(), Response::HTTP_CONFLICT);
        } catch (InvalidFiscalProfile $e) {
            return $this->error('INVALID_FISCAL_PROFILE', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(FiscalProfileOutDto::from($profile), Response::HTTP_CREATED);
    }

    #[Route('/{countryCode}', name: 'market_fiscal_profiles_update', methods: ['PUT'], requirements: ['countryCode' => '[A-Z]{2}'])]
    #[DashboardEndpoint(summary: 'Actualizar el registro fiscal de un país', tag: 'Market', requestDto: UpdateFiscalProfileDto::class, responseDto: FiscalProfileOutDto::class)]
    public function update(string $countryCode, UpdateFiscalProfileDto $dto): JsonResponse
    {
        try {
            $profile = ($this->updateProfile)($countryCode, (string) $dto->getName(), $this->inputs($dto->getFields()));
        } catch (FiscalProfileNotFound $e) {
            return $this->error('FISCAL_PROFILE_NOT_FOUND', $e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (InvalidFiscalProfile $e) {
            return $this->error('INVALID_FISCAL_PROFILE', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(FiscalProfileOutDto::from($profile));
    }

    /**
     * @param FiscalFieldDto[]|null $fields
     * @return list<FiscalFieldInput>
     */
    private function inputs(?array $fields): array
    {
        return array_values(array_map(
            static fn (FiscalFieldDto $f) => new FiscalFieldInput(
                key: (string) $f->getKey(),
                label: (string) $f->getLabel(),
                type: (string) $f->getType(),
                required: $f->getRequired() ?? false,
                pattern: $f->getPattern(),
                helpText: $f->getHelpText(),
                options: array_values($f->getOptions() ?? []),
                active: $f->getActive() ?? true,
                position: $f->getPosition() ?? 0,
            ),
            $fields ?? [],
        ));
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
