<?php

namespace App\Entity;

use App\Repository\CommunicationContractRepository;
use App\Service\Pricing\ServiceCategoryKey;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contrato de precio/visibilidad para un `(tenant, destinationAmount,
 * destinationCurrency)` (rediseño V2, ManyToMany desde 2026-08-18 — ver
 * docs de la Fase 6). `destinationAmount`/`destinationCurrency` son la
 * IDENTIDAD del contrato (con `tenant`, definen su tupla única mientras
 * esté abierto) — YA NO son solo snapshot: `CommunicationContractService::
 * upsertContract()` deduplica por esta tupla, así que dos
 * `CommunicationPackage` distintos que compartan monto y tenant terminan en
 * el MISMO contrato (`$packages`), a propósito — es lo que permite que un
 * paquete promocional a un monto ya cubierto por el catálogo normal
 * aparezca junto a él sin tocar la precedencia de `PackageCatalogResolver`.
 *
 * `tenant === null` = contrato "por defecto": aplica a cualquier cuenta sin
 * contrato propio para esta tupla, con el mismo efecto de
 * visibilidad/precio que uno específico (ver PackageCatalogResolver, Fase
 * 2). Sin `isActive`: la vigencia es solo `startAt`/`endAt` — "pausar" es
 * cerrar (`endAt = now()`) y crear uno nuevo si se reactiva.
 *
 * `serviceName`/`subserviceName`/`serviceKey` (Fase 1 del rediseño de
 * contratos por categoría, agosto 2026): clasificación del contrato, mismo
 * vocabulario que `CommunicationPackage::$service`. Todavía NO forman parte
 * de la identidad/unicidad del contrato ni son usados por
 * `PackageCatalogResolver` — eso es Fase 3, deliberadamente separada porque
 * cambia qué contrato termina cubriendo qué paquete (dato, no solo
 * comportamiento de lectura). Por ahora son solo clasificación informativa,
 * derivada del paquete que se le vincula (ver
 * CommunicationContractService::upsertContract()).
 */
#[ORM\Entity(repositoryClass: CommunicationContractRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CommunicationContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Todos los CommunicationPackage que este contrato cubre — cualquiera
     * que comparta `(destinationAmount, destinationCurrency)` con el
     * contrato en el momento en que se le asoció (ver upsertContract()).
     * Lado propietario de la relación (la tabla puente es
     * communication_contract_package).
     *
     * @var Collection<int, CommunicationPackage>
     */
    #[ORM\ManyToMany(targetEntity: CommunicationPackage::class)]
    #[ORM\JoinTable(name: 'communication_contract_package')]
    private Collection $packages;

    /**
     * null = contrato "por defecto" (aplica a cualquier cuenta sin contrato
     * propio para esta tupla).
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Account $tenant = null;

    /**
     * Identidad del contrato junto con `tenant` — ver docblock de clase.
     */
    #[ORM\Column]
    private ?float $destinationAmount = null;

    #[ORM\Column(length: 10)]
    private ?string $destinationCurrency = null;

    /**
     * Clasificación (Fase 1 del rediseño de contratos por categoría) —
     * mismo shape de nombre/subservicio que CommunicationPackage::$service,
     * pero guardado como columnas planas (no JSON): a diferencia del
     * paquete, aquí no hay un shape más rico que preservar, y así
     * `serviceKey` se deriva de una fuente única sin riesgo de que un JSON
     * independiente se desincronice. Se setean juntos vía
     * setServiceCategory() — nunca `$serviceKey` sola.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serviceName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subserviceName = null;

    /**
     * Derivada de $serviceName/$subserviceName vía ServiceCategoryKey — SE
     * DERIVA EN EL SETTER (ver setServiceCategory()), nunca en un
     * #[ORM\PreUpdate]: Doctrine ya calculó el changeset cuando corre
     * preUpdate, así que mutar la entidad ahí no persiste sin
     * PreUpdateEventArgs::setNewValue() (innecesariamente complejo).
     * Derivarla en el setter es correcto y trivial. Default `'|'` (no
     * `''`) como resguardo — coincide con lo que produce
     * `ServiceCategoryKey::of(null, null)`, aunque el constructor ya llama
     * a setServiceCategory(null, null) como fuente de verdad real.
     */
    #[ORM\Column(length: 191)]
    private string $serviceKey = '|';

    /**
     * Lo que se le cobra al cliente. CHECK >= 0 a nivel de esquema (un
     * contrato en $0 es un precio real y deliberado, ej. cortesía).
     */
    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Auditoría — mueve dinero real. ON DELETE SET NULL: si el usuario que
     * lo creó se borra, el contrato sigue vigente.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->startAt = new \DateTimeImmutable();
        $this->packages = new ArrayCollection();
        // Vía setServiceCategory(), no asignación directa — mismo criterio
        // que CommunicationPackage::__construct(): un único punto deriva
        // $serviceKey, el default de la propiedad es solo un resguardo.
        $this->setServiceCategory(null, null);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, CommunicationPackage>
     */
    public function getPackages(): Collection
    {
        return $this->packages;
    }

    public function addPackage(CommunicationPackage $package): static
    {
        if (!$this->packages->contains($package)) {
            $this->packages->add($package);
        }

        return $this;
    }

    public function removePackage(CommunicationPackage $package): static
    {
        $this->packages->removeElement($package);

        return $this;
    }

    public function getTenant(): ?Account
    {
        return $this->tenant;
    }

    public function setTenant(?Account $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getDestinationAmount(): ?float
    {
        return $this->destinationAmount;
    }

    public function setDestinationAmount(float $destinationAmount): static
    {
        $this->destinationAmount = $destinationAmount;

        return $this;
    }

    public function getDestinationCurrency(): ?string
    {
        return $this->destinationCurrency;
    }

    public function setDestinationCurrency(string $destinationCurrency): static
    {
        $this->destinationCurrency = $destinationCurrency;

        return $this;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function getSubserviceName(): ?string
    {
        return $this->subserviceName;
    }

    public function getServiceKey(): string
    {
        return $this->serviceKey;
    }

    /**
     * Única forma de establecer la categoría — deriva $serviceKey en el
     * mismo paso, no hay setServiceKey() independiente (ver docblock de la
     * propiedad).
     */
    public function setServiceCategory(?string $serviceName, ?string $subserviceName): static
    {
        $this->serviceName = $serviceName;
        $this->subserviceName = $subserviceName;
        $this->serviceKey = ServiceCategoryKey::of($serviceName, $subserviceName);

        return $this;
    }

    /**
     * Shape `{name, subservice: {name}}` — mismo formato que
     * CommunicationPackage::getService(), reconstruido desde las columnas
     * planas para que el DTO de salida tenga forma compatible.
     *
     * @return array{name?: string, subservice?: array{name?: string}}
     */
    public function getServiceShape(): array
    {
        if ($this->serviceName === null) {
            return [];
        }

        $shape = ['name' => $this->serviceName];
        if ($this->subserviceName !== null) {
            $shape['subservice'] = ['name' => $this->subserviceName];
        }

        return $shape;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * Vigente en el instante dado: sin fecha de fin, o fin en el futuro, y
     * ya empezado.
     */
    public function isActiveAt(\DateTimeImmutable $now): bool
    {
        if ($this->startAt !== null && $this->startAt > $now) {
            return false;
        }

        return $this->endAt === null || $this->endAt > $now;
    }

    /**
     * "Pausar" un contrato: cerrarlo ahora mismo. No hay `isActive` — cerrar
     * es la única forma de desactivar (crear uno nuevo si se reactiva).
     */
    public function close(?\DateTimeImmutable $at = null): static
    {
        $this->endAt = $at ?? new \DateTimeImmutable();

        return $this;
    }

    #[ORM\PrePersist]
    public function onCreated(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PostPersist]
    #[ORM\PreUpdate]
    #[ORM\PreFlush]
    public function onUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
