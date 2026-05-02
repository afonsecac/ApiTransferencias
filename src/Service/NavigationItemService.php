<?php

namespace App\Service;

use App\DTO\CreateNavigationItemDto;
use App\DTO\CreateUserPermissionDto;
use App\DTO\UpdateNavigationItemDto;
use App\DTO\UpdateUserPermissionDto;
use App\Entity\Client;
use App\Entity\NavigationItem;
use App\Entity\User;
use App\Entity\UserPermission;
use App\Enums\NavigationTypeEnum;
use App\Exception\MyCurrentException;
use Doctrine\ORM\EntityManagerInterface;

class NavigationItemService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** @throws MyCurrentException */
    public function createItem(CreateNavigationItemDto $dto): NavigationItem
    {
        $item = new NavigationItem();
        $this->applyItemDto($item, $dto->getTitle(), $dto->getType(), $dto->getSubtitle(), $dto->getIcon(), $dto->getLink(), $dto->getParentId(), $dto->getActive(), $dto->getOrderValue(), $dto->getBadge(), $dto->getData());
        $item->setCreatedAt(new \DateTimeImmutable('now'));
        $item->setUpdatedAt(new \DateTimeImmutable('now'));

        $this->em->persist($item);

        if ($dto->getMinRoleRequired() !== null) {
            $permission = new UserPermission();
            $permission->setItem($item);
            $permission->setMinRoleRequired($dto->getMinRoleRequired());
            $permission->setActive(true);
            if ($dto->getClientId() !== null) {
                $permission->setClient($this->em->getRepository(Client::class)->find($dto->getClientId()));
            }
            if ($dto->getUserId() !== null) {
                $permission->setUserInfo($this->em->getRepository(User::class)->find($dto->getUserId()));
            }
            $this->em->persist($permission);
        }

        $this->em->flush();

        return $item;
    }

    public function updateItem(NavigationItem $item, UpdateNavigationItemDto $dto): NavigationItem
    {
        $this->applyItemDto($item, $dto->getTitle(), $dto->getType(), $dto->getSubtitle(), $dto->getIcon(), $dto->getLink(), $dto->getParentId(), $dto->getActive(), $dto->getOrderValue(), $dto->getBadge(), $dto->getData());
        $item->setUpdatedAt(new \DateTimeImmutable('now'));

        $this->em->flush();

        return $item;
    }

    public function deleteItem(NavigationItem $item): void
    {
        foreach ($item->getUserPermissions() as $permission) {
            $this->em->remove($permission);
        }
        $this->em->remove($item);
        $this->em->flush();
    }

    /** @throws MyCurrentException */
    public function createPermission(NavigationItem $item, CreateUserPermissionDto $dto): UserPermission
    {
        $permission = new UserPermission();
        $permission->setItem($item);
        $permission->setMinRoleRequired($dto->getMinRoleRequired());
        $permission->setActive($dto->getIsActive() ?? true);

        if ($dto->getClientId() !== null) {
            $permission->setClient($this->em->getRepository(Client::class)->find($dto->getClientId()));
        }
        if ($dto->getUserId() !== null) {
            $permission->setUserInfo($this->em->getRepository(User::class)->find($dto->getUserId()));
        }

        $this->em->persist($permission);
        $this->em->flush();

        return $permission;
    }

    public function updatePermission(UserPermission $permission, UpdateUserPermissionDto $dto): UserPermission
    {
        if ($dto->getMinRoleRequired() !== null) {
            $permission->setMinRoleRequired($dto->getMinRoleRequired());
        }
        if ($dto->getIsActive() !== null) {
            $permission->setActive($dto->getIsActive());
        }
        if ($dto->getClientId() !== null) {
            $permission->setClient($this->em->getRepository(Client::class)->find($dto->getClientId()));
        }
        if ($dto->getUserId() !== null) {
            $permission->setUserInfo($this->em->getRepository(User::class)->find($dto->getUserId()));
        }

        $this->em->flush();

        return $permission;
    }

    public function deletePermission(UserPermission $permission): void
    {
        $this->em->remove($permission);
        $this->em->flush();
    }

    private function applyItemDto(
        NavigationItem $item,
        ?string $title,
        ?string $type,
        ?string $subtitle,
        ?string $icon,
        ?string $link,
        ?int $parentId,
        ?bool $active,
        ?string $orderValue,
        ?array $badge,
        ?array $data,
    ): void {
        if ($title !== null) {
            $item->setTitle(mb_substr($title, 0, 255));
        }
        if ($subtitle !== null) {
            $item->setSubtitle(mb_substr($subtitle, 0, 255));
        }
        if ($type !== null) {
            $item->setType(NavigationTypeEnum::tryFrom($type));
        }
        if ($icon !== null) {
            $item->setIcon(mb_substr($icon, 0, 80));
        }
        if ($link !== null) {
            $item->setLink(mb_substr($link, 0, 255));
        }
        if ($parentId !== null) {
            $item->setParent($this->em->getRepository(NavigationItem::class)->find($parentId));
        } elseif ($item->getId() !== null) {
            $item->setParent(null);
        }
        if ($active !== null) {
            $item->setActive($active);
        } elseif ($item->active() === null) {
            $item->setActive(true);
        }
        if ($orderValue !== null) {
            $item->setOrderValue(mb_substr($orderValue, 0, 5));
        }
        if ($badge !== null) {
            $item->setBadge($badge);
        }
        if ($data !== null) {
            $item->setData($data);
        }
    }
}
