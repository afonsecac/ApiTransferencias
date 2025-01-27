<?php

namespace App\Entity;

use App\Enums\NavigationTypeEnum;
use App\Repository\NavigationItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: NavigationItemRepository::class)]
class NavigationItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups('navigation')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups('navigation')]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('navigation')]
    private ?string $subtitle = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups('navigation')]
    private ?NavigationTypeEnum $type = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Groups('navigation')]
    private ?string $icon = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('navigation')]
    private ?string $link = null;

    #[ORM\Column(nullable: true)]
    #[Groups('navigation')]
    private ?array $badge = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[Groups('navigation')]
    private Collection $children;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private ?bool $active = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups('navigation')]
    private ?array $data = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $orderValue = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('navigation')]
    private ?string $fragment = null;

    #[ORM\Column(nullable: true)]
    #[Groups('navigation')]
    private ?bool $preserveFragment = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('navigation')]
    private ?string $queryParams = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('navigation')]
    private ?string $queryParamsHandling = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('navigation')]
    private ?bool $externalLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('navigation')]
    private ?string $target = null;

    #[ORM\Column(nullable: true)]
    #[Groups('navigation')]
    private ?bool $exactMatch = null;

    #[ORM\Column(nullable: true)]
    #[Groups('navigation')]
    private ?array $isActiveMatchOptions = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('navigation')]
    private ?string $function = null;

    #[ORM\Column(nullable: true)]
    #[Groups('navigation')]
    private ?array $classes = null;

    #[ORM\Column(nullable: true)]
    #[Groups('navigation')]
    private ?array $meta = null;

    #[ORM\Column(nullable: true)]
    #[Groups('navigation')]
    private ?bool $disabled = null;

    #[ORM\Column(nullable: true)]
    private ?array $translateName = null;

    #[ORM\Column(nullable: true)]
    private ?array $translateSubTitle = null;

    #[ORM\Column(nullable: true)]
    private ?array $permissions = null;

    /**
     * @var Collection<int, UserPermission>
     */
    #[ORM\OneToMany(targetEntity: UserPermission::class, mappedBy: 'item')]
    private Collection $userPermissions;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->userPermissions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function getType(): ?NavigationTypeEnum
    {
        return $this->type;
    }

    public function setType(?NavigationTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getBadge(): ?array
    {
        return $this->badge;
    }

    public function setBadge(?array $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function active(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $isActive): static
    {
        $this->active = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getOrderValue(): ?string
    {
        return $this->orderValue;
    }

    public function setOrderValue(?string $orderValue): static
    {
        $this->orderValue = $orderValue;

        return $this;
    }

    public function getFragment(): ?string
    {
        return $this->fragment;
    }

    public function setFragment(?string $fragment): static
    {
        $this->fragment = $fragment;

        return $this;
    }

    public function isPreserveFragment(): ?bool
    {
        return $this->preserveFragment;
    }

    public function setPreserveFragment(?bool $preserveFragment): static
    {
        $this->preserveFragment = $preserveFragment;

        return $this;
    }

    public function getQueryParams(): ?string
    {
        return $this->queryParams;
    }

    public function setQueryParams(?string $queryParams): static
    {
        $this->queryParams = $queryParams;

        return $this;
    }

    public function getQueryParamsHandling(): ?string
    {
        return $this->queryParamsHandling;
    }

    public function setQueryParamsHandling(?string $queryParamsHandling): static
    {
        $this->queryParamsHandling = $queryParamsHandling;

        return $this;
    }

    public function getExternalLink(): ?bool
    {
        return $this->externalLink;
    }

    public function setExternalLink(?bool $externalLink): static
    {
        $this->externalLink = $externalLink;

        return $this;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(?string $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function exactMatch(): ?bool
    {
        return $this->exactMatch;
    }

    public function setExactMatch(?bool $exactMatch): static
    {
        $this->exactMatch = $exactMatch;

        return $this;
    }

    public function getIsActiveMatchOptions(): ?array
    {
        return $this->isActiveMatchOptions;
    }

    public function setIsActiveMatchOptions(?array $isActiveMatchOptions): static
    {
        $this->isActiveMatchOptions = $isActiveMatchOptions;

        return $this;
    }

    public function getFunction(): ?string
    {
        return $this->function;
    }

    public function setFunction(?string $function): static
    {
        $this->function = $function;

        return $this;
    }

    public function getClasses(): ?array
    {
        return $this->classes;
    }

    public function setClasses(?array $classes): static
    {
        $this->classes = $classes;

        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    public function disabled(): ?bool
    {
        return $this->disabled;
    }

    public function setDisabled(?bool $disabled): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function getTranslateName(): ?array
    {
        return $this->translateName;
    }

    public function setTranslateName(?array $translateName): static
    {
        $this->translateName = $translateName;

        return $this;
    }

    public function getTranslateSubTitle(): ?array
    {
        return $this->translateSubTitle;
    }

    public function setTranslateSubTitle(?array $translateSubTitle): static
    {
        $this->translateSubTitle = $translateSubTitle;

        return $this;
    }

    public function getPermissions(): ?array
    {
        return $this->permissions;
    }

    public function setPermissions(?array $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    /**
     * @return Collection<int, UserPermission>
     */
    public function getUserPermissions(): Collection
    {
        return $this->userPermissions;
    }

    public function addUserPermission(UserPermission $userPermission): static
    {
        if (!$this->userPermissions->contains($userPermission)) {
            $this->userPermissions->add($userPermission);
            $userPermission->setItem($this);
        }

        return $this;
    }

    public function removeUserPermission(UserPermission $userPermission): static
    {
        if ($this->userPermissions->removeElement($userPermission)) {
            // set the owning side to null (unless already changed)
            if ($userPermission->getItem() === $this) {
                $userPermission->setItem(null);
            }
        }

        return $this;
    }
}
