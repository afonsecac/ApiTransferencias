<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        if (!$user->isActive()) {
            throw new DisabledException('Your account is not active.');
        }
        if (!$user->isCheckValidation()) {
            throw new CustomUserMessageAccountStatusException('Your account is pending validation.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        $currentDate = new \DateTimeImmutable('now');
        if ($user->getRemovedAt() !== null && $user->getRemovedAt() <= $currentDate) {
            throw new AccountExpiredException('Your account has been removed.');
        }
    }
}
