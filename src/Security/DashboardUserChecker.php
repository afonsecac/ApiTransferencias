<?php

namespace App\Security;

use ApiPlatform\Symfony\Security\Exception\AccessDeniedException;
use App\Entity\User;
use App\Service\UserService;
use Symfony\Component\Config\Definition\Exception\ForbiddenOverwriteException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardUserChecker implements UserCheckerInterface
{
    public function __construct(private readonly UserService $userService)
    {

    }

    /**
     * @inheritDoc
     */
    public function checkPreAuth(UserInterface $user): void
    {
        // TODO: Implement checkPreAuth() method.
        if (!$user instanceof User) {
            throw new AccessDeniedException("You are not authorized to access this resource");
        }
        if (!$user->isActive()) {
            throw new AccessDeniedException("Your account is not active");
        }
        if (!$user->isCheckValidation()) {
            throw new ForbiddenOverwriteException("Your account is not valid");
        }
    }

    /**
     * @inheritDoc
     */
    public function checkPostAuth(UserInterface $user): void
    {
        $currentDate = new \DateTimeImmutable('now');
        if (!$user instanceof User || ($user->getRemovedAt() !== null && $user->getRemovedAt() <= $currentDate)) {
            throw new AccessDeniedException("You are not allowed to access this resource");
        }
    }
}