<?php

namespace App\Service;

use App\DTO\CreateProviderRoutingDto;
use App\DTO\Out\ProviderRoutingPreviewOutDto;
use App\DTO\UpdateProviderRoutingDto;
use App\Entity\Client;
use App\Entity\ClientProviderRouting;
use App\Entity\CommunicationSaleInfo;
use App\Entity\Environment;
use App\Entity\User;
use App\Enums\CommunicationProviderEnum;
use App\Enums\CommunicationStateEnum;
use App\Exception\MyCurrentException;
use App\Provider\ProviderCredentialsResolver;
use App\Provider\ProviderResolver;
use App\Repository\ClientProviderRoutingRepository;
use App\Service\Provider\ClientCatalogImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD administrativo de client_provider_routing (Fase 2). Escribir aquí es
 * mover dinero real de un cliente a otro proveedor: toda mutación invalida
 * la caché de resolución que consume el camino caliente de despacho.
 */
class ProviderRoutingAdminService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientProviderRoutingRepository $routingRepo,
        private readonly ProviderResolver $providerResolver,
        private readonly Security $security,
        private readonly ClientCatalogImportService $catalogImportService,
        private readonly ProviderCredentialsResolver $credentialsResolver,
    ) {
    }

    public function create(CreateProviderRoutingDto $dto): ClientProviderRouting
    {
        $client = $this->em->getRepository(Client::class)->find($dto->getClientId());
        if ($client === null) {
            throw new MyCurrentException('CLIENT_NOT_FOUND', 'Cliente no encontrado', Response::HTTP_NOT_FOUND);
        }

        $environment = $this->resolveEnvironmentOrFail($dto->getEnvironmentId());

        $this->assertScopeIsFree($client->getId(), $dto->getEnvironmentId(), $dto->getSaleType());
        $this->assertProviderIsEnabled($dto->getProvider(), $environment?->getType());

        $routing = new ClientProviderRouting();
        $routing->setClient($client);
        $routing->setEnvironment($environment);
        $routing->setSaleType($dto->getSaleType());
        $routing->setProvider($dto->getProvider());
        $routing->setFallbackProvider($dto->getFallbackProvider());
        $routing->setNotes($dto->getNotes());

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $routing->setCreatedBy($user);
        }

        $this->em->persist($routing);
        $this->em->flush();
        $this->routingRepo->invalidateCache();

        // Asistido, best-effort: un fallo aquí no debe impedir que el
        // routing quede creado (ver ClientCatalogImportService).
        $this->catalogImportService->importForRouting($routing);

        return $routing;
    }

    public function update(ClientProviderRouting $routing, UpdateProviderRoutingDto $dto): ClientProviderRouting
    {
        $providerChanged = $dto->getProvider() !== null && $dto->getProvider() !== $routing->getProvider();
        $scopeTouched = $dto->getEnvironmentId() !== null || $dto->getSaleType() !== null;

        $effectiveEnvironment = $routing->getEnvironment();

        if ($scopeTouched) {
            $environment = $dto->getEnvironmentId() !== null
                ? $this->resolveEnvironmentOrFail($dto->getEnvironmentId())
                : $routing->getEnvironment();
            $saleType = $dto->getSaleType() ?? $routing->getSaleType();

            $this->assertScopeIsFree(
                $routing->getClient()->getId(),
                $environment?->getId(),
                $saleType,
                excludeId: $routing->getId(),
            );

            $routing->setEnvironment($environment);
            $routing->setSaleType($saleType);
            $effectiveEnvironment = $environment;
        }

        if ($providerChanged) {
            $this->assertProviderIsEnabled($dto->getProvider(), $effectiveEnvironment?->getType());
        }

        if ($dto->getProvider() !== null) {
            $routing->setProvider($dto->getProvider());
        }
        if ($dto->getFallbackProvider() !== null) {
            $routing->setFallbackProvider($dto->getFallbackProvider());
        }
        if ($dto->getNotes() !== null) {
            $routing->setNotes($dto->getNotes());
        }
        if ($dto->getIsActive() !== null) {
            $this->applyActive($routing, $dto->getIsActive());
        }

        $routing->touch();
        $this->em->flush();
        $this->routingRepo->invalidateCache();

        if ($providerChanged) {
            $this->catalogImportService->importForRouting($routing);
        }

        return $routing;
    }

    public function toggle(ClientProviderRouting $routing): ClientProviderRouting
    {
        $this->applyActive($routing, !$routing->isActive());
        $routing->touch();
        $this->em->flush();
        $this->routingRepo->invalidateCache();

        return $routing;
    }

    public function delete(ClientProviderRouting $routing): void
    {
        $this->em->remove($routing);
        $this->em->flush();
        $this->routingRepo->invalidateCache();
    }

    /**
     * Impacto de un cambio ANTES de aplicarlo. affectedPackagesCount queda
     * en null hasta la Fase 3 (communication_product.provider aún no
     * existe, así que hoy no hay forma de saber a qué proveedor pertenece
     * cada CommunicationProduct).
     */
    public function preview(int $clientId, ?int $environmentId, ?string $saleType, string $proposedProvider): ProviderRoutingPreviewOutDto
    {
        $client = $this->em->getRepository(Client::class)->find($clientId);
        if ($client === null) {
            throw new MyCurrentException('CLIENT_NOT_FOUND', 'Cliente no encontrado', Response::HTTP_NOT_FOUND);
        }

        $currentProvider = $this->providerResolver->resolveEffectiveFor($clientId, $environmentId, $saleType);

        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(CommunicationSaleInfo::class, 's')
            ->join('s.tenant', 't')
            ->andWhere('t.client = :clientId')
            ->andWhere('s.state = :pending')
            ->setParameter('clientId', $clientId)
            ->setParameter('pending', CommunicationStateEnum::PENDING->value);

        if ($environmentId !== null) {
            $qb->andWhere('t.environment = :environmentId')->setParameter('environmentId', $environmentId);
        }

        $pendingSalesCount = (int) $qb->getQuery()->getSingleScalarResult();

        $preview = new ProviderRoutingPreviewOutDto();
        $preview->currentEffectiveProvider = $currentProvider->value;
        $preview->proposedEffectiveProvider = $proposedProvider;
        $preview->pendingSalesCount = $pendingSalesCount;
        $preview->proposedProviderUnregistered = false;

        return $preview;
    }

    /**
     * La unique index parcial de la BD (uniq_cpr_scope, WHERE is_active) es
     * la garantía final, pero validar aquí antes de intentar el flush da un
     * error 409 legible en vez de una excepción de Doctrine sin traducir.
     */
    private function assertScopeIsFree(int $clientId, ?int $environmentId, ?string $saleType, ?int $excludeId = null): void
    {
        $qb = $this->em->getRepository(ClientProviderRouting::class)->createQueryBuilder('cpr')
            ->andWhere('cpr.client = :clientId')
            ->andWhere('cpr.isActive = true')
            ->setParameter('clientId', $clientId);

        $qb->andWhere($environmentId !== null ? 'cpr.environment = :environmentId' : 'cpr.environment IS NULL');
        if ($environmentId !== null) {
            $qb->setParameter('environmentId', $environmentId);
        }

        $qb->andWhere($saleType !== null ? 'cpr.saleType = :saleType' : 'cpr.saleType IS NULL');
        if ($saleType !== null) {
            $qb->setParameter('saleType', $saleType);
        }

        if ($excludeId !== null) {
            $qb->andWhere('cpr.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        $existing = $qb->getQuery()->getOneOrNullResult();
        if ($existing !== null) {
            throw new MyCurrentException(
                'PROVIDER_ROUTING_DUPLICATE',
                'Ya existe un enrutado activo para este cliente/entorno/tipo de venta.',
                Response::HTTP_CONFLICT,
            );
        }
    }

    /**
     * Gate de habilitación: un proveedor solo se puede asignar explícitamente
     * a un cliente/entorno si (a) tiene configuradas todas sus claves
     * obligatorias (ProviderCredentialsResolver::isFullyConfigured(), según
     * el esquema de CommunicationProviderInterface::getConfigSchema()) y
     * (b) no está apagado manualmente para ese entorno
     * (ProviderCredentialsResolver::isActive() — interruptor administrativo
     * independiente de si las claves están cargadas).
     *
     * Con $environmentType null (el enrutado aplica a ambos entornos del
     * cliente) se exigen ambas condiciones en TEST y en PROD — esa fila
     * decidirá el proveedor para ventas en cualquiera de los dos.
     */
    private function assertProviderIsEnabled(?string $providerCode, ?string $environmentType): void
    {
        if ($providerCode === null) {
            return;
        }

        $provider = CommunicationProviderEnum::tryFrom($providerCode);
        if ($provider === null) {
            return;
        }

        $environmentTypesToCheck = $environmentType !== null ? [$environmentType] : ['TEST', 'PROD'];

        foreach ($environmentTypesToCheck as $envType) {
            if (!$this->credentialsResolver->isActive($provider, $envType)) {
                throw new MyCurrentException(
                    'PROVIDER_INACTIVE',
                    "El proveedor {$provider->value} está desactivado manualmente para el entorno {$envType}",
                    Response::HTTP_CONFLICT,
                );
            }

            if (!$this->credentialsResolver->isFullyConfigured($provider, $envType)) {
                throw new MyCurrentException(
                    'PROVIDER_NOT_CONFIGURED',
                    "El proveedor {$provider->value} no tiene configuradas sus claves obligatorias para el entorno {$envType}",
                    Response::HTTP_CONFLICT,
                );
            }
        }
    }

    private function applyActive(ClientProviderRouting $routing, bool $active): void
    {
        if ($active && !$routing->isActive()) {
            $this->assertScopeIsFree(
                $routing->getClient()->getId(),
                $routing->getEnvironment()?->getId(),
                $routing->getSaleType(),
                excludeId: $routing->getId(),
            );
        }
        $routing->setIsActive($active);
    }

    private function resolveEnvironmentOrFail(?int $environmentId): ?Environment
    {
        if ($environmentId === null) {
            return null;
        }

        $environment = $this->em->getRepository(Environment::class)->find($environmentId);
        if ($environment === null) {
            throw new MyCurrentException('ENVIRONMENT_NOT_FOUND', 'Entorno no encontrado', Response::HTTP_NOT_FOUND);
        }

        return $environment;
    }
}
