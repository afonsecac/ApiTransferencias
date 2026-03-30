<?php

namespace App\Tests\Service;

use App\Entity\NavigationItem;
use App\Entity\User;
use App\Entity\Client;
use App\Repository\EnvironmentRepository;
use App\Repository\NavigationItemRepository;
use App\Repository\SysConfigRepository;
use App\Service\NavigationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @covers \App\Service\NavigationService
 */
class NavigationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private RoleHierarchyInterface&MockObject $roleHierarchy;
    private NavigationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->roleHierarchy = $this->createMock(RoleHierarchyInterface::class);

        $parameters = $this->createMock(ParameterBagInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $environmentRepository = $this->createMock(EnvironmentRepository::class);
        $sysConfigRepo = $this->createMock(SysConfigRepository::class);
        $serializer = $this->createMock(SerializerInterface::class);

        $this->service = new NavigationService(
            $this->em,
            $this->security,
            $parameters,
            $mailer,
            $logger,
            $passwordHasher,
            $environmentRepository,
            $sysConfigRepo,
            $serializer,
            $this->roleHierarchy,
        );
    }

    public function testGetNavigationItemsReturnsArrayFromRepository(): void
    {
        $items = [
            $this->createMock(NavigationItem::class),
            $this->createMock(NavigationItem::class),
        ];

        $navRepo = $this->createMock(NavigationItemRepository::class);
        $navRepo->method('getNavigationItems')->willReturn($items);

        $this->em->method('getRepository')
            ->with(NavigationItem::class)
            ->willReturn($navRepo);

        $result = $this->service->getNavigationItems();

        $this->assertSame($items, $result);
        $this->assertCount(2, $result);
    }

    public function testGetNavigationItemsReturnsEmptyArray(): void
    {
        $navRepo = $this->createMock(NavigationItemRepository::class);
        $navRepo->method('getNavigationItems')->willReturn([]);

        $this->em->method('getRepository')
            ->with(NavigationItem::class)
            ->willReturn($navRepo);

        $result = $this->service->getNavigationItems();

        $this->assertSame([], $result);
    }

    public function testGetNavigationForUsersThrowsWhenNotUser(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->expectException(AccessDeniedException::class);

        $this->service->getNavigationForUsers();
    }

    public function testGetNavigationForUsersAsAdmin(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $user->method('getCompany')->willReturn(null);
        $user->method('getId')->willReturn(1);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(true);

        $this->roleHierarchy->method('getReachableRoleNames')
            ->willReturn(['ROLE_ADMIN', 'ROLE_USER']);

        $child = $this->createMock(NavigationItem::class);
        $child->method('getId')->willReturn(10);

        $children = new \Doctrine\Common\Collections\ArrayCollection([$child]);

        $item = $this->createMock(NavigationItem::class);
        $item->method('getChildren')->willReturn($children);
        $item->method('getLink')->willReturn('/dashboard');

        $navRepo = $this->createMock(NavigationItemRepository::class);
        $navRepo->method('getAllowedItemIds')->willReturn([10]);
        $navRepo->method('getNavigationByUserAndClient')->willReturn([$item]);

        $this->em->method('getRepository')
            ->with(NavigationItem::class)
            ->willReturn($navRepo);

        $result = $this->service->getNavigationForUsers();

        $this->assertCount(1, $result);
    }

    public function testGetNavigationForUsersFiltersUnauthorizedChildren(): void
    {
        $company = $this->createMock(Client::class);
        $company->method('getId')->willReturn(5);

        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('getCompany')->willReturn($company);
        $user->method('getId')->willReturn(2);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $this->roleHierarchy->method('getReachableRoleNames')
            ->willReturn(['ROLE_USER']);

        $allowedChild = $this->createMock(NavigationItem::class);
        $allowedChild->method('getId')->willReturn(10);

        $disallowedChild = $this->createMock(NavigationItem::class);
        $disallowedChild->method('getId')->willReturn(20);

        $children = new \Doctrine\Common\Collections\ArrayCollection([$allowedChild, $disallowedChild]);

        $item = $this->createMock(NavigationItem::class);
        $item->method('getChildren')->willReturn($children);
        $item->method('getLink')->willReturn(null);
        $item->expects($this->once())->method('removeChild')->with($disallowedChild);

        $navRepo = $this->createMock(NavigationItemRepository::class);
        $navRepo->method('getAllowedItemIds')->willReturn([10]);
        $navRepo->method('getNavigationByUserAndClient')->willReturn([$item]);

        $this->em->method('getRepository')
            ->with(NavigationItem::class)
            ->willReturn($navRepo);

        $this->service->getNavigationForUsers();
    }

    public function testGetNavigationForUsersRemovesParentsWithNoChildrenAndNoLink(): void
    {
        $company = $this->createMock(Client::class);
        $company->method('getId')->willReturn(5);

        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('getCompany')->willReturn($company);
        $user->method('getId')->willReturn(2);

        $this->security->method('getUser')->willReturn($user);
        $this->security->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $this->roleHierarchy->method('getReachableRoleNames')
            ->willReturn(['ROLE_USER']);

        $emptyChildren = new \Doctrine\Common\Collections\ArrayCollection();

        $itemWithLink = $this->createMock(NavigationItem::class);
        $itemWithLink->method('getChildren')->willReturn($emptyChildren);
        $itemWithLink->method('getLink')->willReturn('/some-page');

        $itemWithoutLink = $this->createMock(NavigationItem::class);
        $itemWithoutLink->method('getChildren')->willReturn($emptyChildren);
        $itemWithoutLink->method('getLink')->willReturn(null);

        $navRepo = $this->createMock(NavigationItemRepository::class);
        $navRepo->method('getAllowedItemIds')->willReturn([]);
        $navRepo->method('getNavigationByUserAndClient')->willReturn([$itemWithLink, $itemWithoutLink]);

        $this->em->method('getRepository')
            ->with(NavigationItem::class)
            ->willReturn($navRepo);

        $result = $this->service->getNavigationForUsers();

        $this->assertCount(1, $result);
    }
}
