<?php

declare(strict_types=1);

namespace App\Tests\Market\Inventario\Application;

use App\Market\Inventario\Domain\StaffAlreadyActivated;
use App\Market\Inventario\Domain\StaffMember;
use App\Market\Inventario\Domain\WarehouseStaff;

final class FakeWarehouseStaff implements WarehouseStaff
{
    /** @var array<int, StaffMember> */
    public array $members = [];
    public int $invitations = 0;
    public int $resends = 0;
    public ?bool $lastInviteExternal = null;
    private int $nextId = 100;

    public function add(int $id, string $email, bool $activated = true, bool $twoFactor = false, bool $external = false): void
    {
        $this->members[$id] = new StaffMember($id, $email, 'Ana Pérez', $activated, $twoFactor, $external);
    }

    public function findByEmail(string $email): ?StaffMember
    {
        foreach ($this->members as $member) {
            if (strcasecmp($member->email, $email) === 0) {
                return $member;
            }
        }

        return null;
    }

    public function findMany(array $ids): array
    {
        return array_intersect_key($this->members, array_flip($ids));
    }

    public function invite(string $email, string $firstName, string $lastName, ?int $inviterId, bool $external = false): StaffMember
    {
        ++$this->invitations;
        $this->lastInviteExternal = $external;
        $id = $this->nextId++;
        $this->members[$id] = new StaffMember($id, $email, trim($firstName . ' ' . $lastName), false, false, $external);

        return $this->members[$id];
    }

    public function resendInvitation(int $userId): void
    {
        if ($this->members[$userId]->activated) {
            throw StaffAlreadyActivated::forUser($userId);
        }
        ++$this->resends;
    }
}
