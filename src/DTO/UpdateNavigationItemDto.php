<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateNavigationItemDto implements IInput
{
    #[Assert\Length(max: 255)]
    protected ?string $title;

    protected ?string $type;

    #[Assert\Length(max: 255)]
    protected ?string $subtitle;

    #[Assert\Length(max: 80)]
    protected ?string $icon;

    #[Assert\Length(max: 255)]
    protected ?string $link;

    protected ?int $parentId;

    protected ?bool $active;

    #[Assert\Length(max: 5)]
    protected ?string $orderValue;

    protected ?array $badge;

    protected ?array $data;

    public function __construct(
        ?string $title = null,
        ?string $type = null,
        ?string $subtitle = null,
        ?string $icon = null,
        ?string $link = null,
        ?int $parentId = null,
        ?bool $active = null,
        ?string $orderValue = null,
        ?array $badge = null,
        ?array $data = null,
    ) {
        $this->title      = $title;
        $this->type       = $type;
        $this->subtitle   = $subtitle;
        $this->icon       = $icon;
        $this->link       = $link;
        $this->parentId   = $parentId;
        $this->active     = $active;
        $this->orderValue = $orderValue;
        $this->badge      = $badge;
        $this->data       = $data;
    }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $v): void { $this->title = $v; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $v): void { $this->type = $v; }

    public function getSubtitle(): ?string { return $this->subtitle; }
    public function setSubtitle(?string $v): void { $this->subtitle = $v; }

    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $v): void { $this->icon = $v; }

    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $v): void { $this->link = $v; }

    public function getParentId(): ?int { return $this->parentId; }
    public function setParentId(?int $v): void { $this->parentId = $v; }

    public function getActive(): ?bool { return $this->active; }
    public function setActive(?bool $v): void { $this->active = $v; }

    public function getOrderValue(): ?string { return $this->orderValue; }
    public function setOrderValue(?string $v): void { $this->orderValue = $v; }

    public function getBadge(): ?array { return $this->badge; }
    public function setBadge(?array $v): void { $this->badge = $v; }

    public function getData(): ?array { return $this->data; }
    public function setData(?array $v): void { $this->data = $v; }
}
