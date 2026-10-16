<?php

declare(strict_types=1);

namespace App\Market\Inventario\Infrastructure\Persistence;

use App\DTO\CreateUserDto;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\Market\Inventario\Domain\InvalidWarehouseUser;
use App\Market\Inventario\Domain\StaffAlreadyActivated;
use App\Market\Inventario\Domain\StaffMember;
use App\Market\Inventario\Domain\WarehouseStaff;
use App\Service\UserManagementService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Puerto de Inventario hacia los usuarios del dashboard: reutiliza la creación de usuarios y el flujo de activación existentes
 * (el correo de activación sale por el mismo mensaje; la contraseña no se fija aquí). El usuario nuevo queda en la empresa de
 * quien invita: es personal de la plataforma.
 */
final class DashboardWarehouseStaff implements WarehouseStaff
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserManagementService $userManagement,
        private readonly UserService $userService,
    ) {
    }

    public function findByEmail(string $email): ?StaffMember
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        return $user === null ? null : self::translate($user);
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $members = [];
        foreach ($this->em->getRepository(User::class)->findBy(['id' => $ids]) as $user) {
            $members[(int) $user->getId()] = self::translate($user);
        }

        return $members;
    }

    public function invite(string $email, string $firstName, string $lastName, ?int $inviterId): StaffMember
    {
        $inviter = $inviterId === null ? null : $this->em->getRepository(User::class)->find($inviterId);
        $companyId = $inviter?->getCompany()?->getId();
        if ($companyId === null) {
            throw new InvalidWarehouseUser('Quien invita no pertenece a ninguna empresa: no se puede crear el usuario nuevo.');
        }

        try {
            $user = $this->userManagement->create(new CreateUserDto(email: $email, firstName: $firstName, lastName: $lastName), $companyId);
        } catch (MyCurrentException $e) {
            throw new InvalidWarehouseUser($e->getMessage());
        }

        return self::translate($user);
    }

    public function resendInvitation(int $userId): void
    {
        $user = $this->em->getRepository(User::class)->find($userId) ?? throw new InvalidWarehouseUser(sprintf('No existe el usuario %d.', $userId));
        try {
            $this->userService->dispatchActivationEmail($user);
        } catch (MyCurrentException $e) {
            if ($e->getCode() === 409) {
                throw StaffAlreadyActivated::forUser($userId);
            }
            throw new InvalidWarehouseUser($e->getMessage());
        }
    }

    private static function translate(User $user): StaffMember
    {
        return new StaffMember(
            (int) $user->getId(),
            (string) $user->getEmail(),
            trim($user->getFirstName() . ' ' . $user->getLastName()),
            $user->isIsCheckValidation() === true,
            $user->isTwoFactorEnabled(),
        );
    }
}
