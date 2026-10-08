<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\EnvironmentRepository;
use App\Repository\SysConfigRepository;
use App\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * El acceso denegado de los informes debe lanzar la excepción de Security (la
 * que el firewall convierte en 403), no la de Finder, que es de ficheros.
 *
 * @covers \App\Service\ReportService
 */
class ReportServiceAccessTest extends TestCase
{
    private Security $security;
    private ReportService $service;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->service = new ReportService(
            $this->createMock(EntityManagerInterface::class),
            $this->security,
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(EnvironmentRepository::class),
            $this->createMock(SysConfigRepository::class),
            $this->createMock(SerializerInterface::class),
        );
    }

    public function testGetAllReportsWithoutAUserThrowsSecurityAccessDenied(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->expectException(AccessDeniedException::class);

        $this->service->getAllReports();
    }

    public function testGetAllReportsWithoutThePermissionThrowsSecurityAccessDenied(): void
    {
        $this->security->method('getUser')->willReturn($this->createMock(User::class));
        $this->security->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);

        $this->service->getAllReports();
    }
}
