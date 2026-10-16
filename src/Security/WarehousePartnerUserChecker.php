<?php

namespace App\Security;

use App\Entity\User;
use App\Service\TwoFactorService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Comprobador del firewall `warehouse` (`/warehouse/api`, COMR-288): las comprobaciones de siempre del dashboard y, además, el 2FA
 * obligatorio del socio de almacén. El login ya no le da sesión sin 2FA; esto cubre también un JWT emitido antes de que se le
 * asignara el rol o de que se le desactivara el segundo factor.
 */
class WarehousePartnerUserChecker implements UserCheckerInterface
{
    public function __construct(private readonly DashboardUserChecker $inner)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        $this->inner->checkPreAuth($user);
        if ($user instanceof User && TwoFactorService::isRoleWithMandatoryTwoFactor($user) && !$user->isTwoFactorEnabled()) {
            throw new CustomUserMessageAccountStatusException('Two-factor authentication is required for this account.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        $this->inner->checkPostAuth($user, $token);
    }
}
