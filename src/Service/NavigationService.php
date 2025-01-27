<?php

namespace App\Service;

use App\Entity\NavigationItem;
use App\Entity\User;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Serializer\SerializerInterface;

class NavigationService extends CommonService
{
    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        ParameterBagInterface $parameters,
        MailerInterface $mailer,
        LoggerInterface $logger,
        UserPasswordHasherInterface $passwordHasher,
        EnvironmentRepository $environmentRepository,
        SysConfigRepository $sysConfigRepo,
        SerializerInterface $serializer,
        protected readonly RoleHierarchyInterface $roleHierarchy,
    )
    {
        parent::__construct(
            $em,
            $security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo,
            $serializer
        );
    }

    /**
     * @return NavigationItem[]
     */
    public function getNavigationItems(): array
    {
        return $this->em->getRepository(NavigationItem::class)->getNavigationItems();
    }

    public function getNavigationForUsers(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $userRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());
        $companyId = $user->getCompany()?->getId();
        $userId = $user->getId();
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $companyId = null;
            $userId = null;
        }


        return $this->em->getRepository(NavigationItem::class)->getNavigationByUserAndClient(
            $userRoles,
            $companyId,
            $userId
        );
    }
}