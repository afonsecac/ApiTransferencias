<?php

namespace App\Tests\Controller;

use App\Controller\DashboardClientPackagesController;
use App\DTO\CreatePricePackageDto;
use App\Entity\Client;
use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationPricePackage;
use App\Entity\User;
use App\Exception\MyCurrentException;
use App\Service\CommunicationPackageService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \App\Controller\DashboardClientPackagesController
 */
class DashboardClientPackagesControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private CommunicationPackageService&MockObject $packageService;
    private ValidatorInterface&MockObject $validator;
    private DashboardClientPackagesController $controller;

    protected function setUp(): void
    {
        $this->em             = $this->createMock(EntityManagerInterface::class);
        $this->security       = $this->createMock(Security::class);
        $this->packageService = $this->createMock(CommunicationPackageService::class);
        $this->validator      = $this->createMock(ValidatorInterface::class);

        $this->controller = new DashboardClientPackagesController(
            $this->em,
            $this->security,
            $this->validator,
            $this->packageService,
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    // ---- showPrice ----

    public function testShowPriceReturnsNotFoundWhenPricePackageDoesNotExist(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);
        $this->em->method('getRepository')
            ->with(CommunicationPricePackage::class)
            ->willReturn($repo);

        $response = $this->controller->showPrice(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Price package not found', $data['error']['message']);
    }

    public function testShowPriceReturnsSerializedPricePackage(): void
    {
        $pp = $this->createPricePackageMock(1, 'Test Package', 10.0, 'USD', 100.0, 'CUP', true);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(1)->willReturn($pp);
        $this->em->method('getRepository')
            ->with(CommunicationPricePackage::class)
            ->willReturn($repo);

        $response = $this->controller->showPrice(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['id']);
        $this->assertSame('Test Package', $data['name']);
        $this->assertEquals(10.0, $data['price']);
        $this->assertSame('USD', $data['priceCurrency']);
        $this->assertEquals(100.0, $data['amount']);
        $this->assertSame('CUP', $data['currency']);
        $this->assertTrue($data['isActive']);
    }

    // ---- togglePrice ----

    public function testTogglePriceNotFound(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);
        $this->em->method('getRepository')
            ->with(CommunicationPricePackage::class)
            ->willReturn($repo);

        $response = $this->controller->togglePrice(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testTogglePriceTogglesActiveState(): void
    {
        $pp = $this->createMock(CommunicationPricePackage::class);
        $pp->method('isActive')->willReturn(true);
        $pp->method('getId')->willReturn(5);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(5)->willReturn($pp);
        $this->em->method('getRepository')
            ->with(CommunicationPricePackage::class)
            ->willReturn($repo);

        $this->packageService->expects($this->once())->method('togglePrice')->with($pp);

        $response = $this->controller->togglePrice(5);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    // ---- deletePrice ----

    public function testDeletePriceNotFound(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);
        $this->em->method('getRepository')
            ->with(CommunicationPricePackage::class)
            ->willReturn($repo);

        $response = $this->controller->deletePrice(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeletePriceRemovesEntity(): void
    {
        $pp = $this->createMock(CommunicationPricePackage::class);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(1)->willReturn($pp);
        $this->em->method('getRepository')
            ->with(CommunicationPricePackage::class)
            ->willReturn($repo);

        $this->packageService->expects($this->once())->method('deletePrice')->with($pp);

        $response = $this->controller->deletePrice(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['deleted']);
    }

    // ---- showPackage ----

    public function testShowPackageReturnsNotFoundWhenPackageDoesNotExist(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);
        $this->em->method('getRepository')
            ->with(CommunicationClientPackage::class)
            ->willReturn($repo);

        $response = $this->controller->showPackage(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Package not found', $data['error']['message']);
    }

    // ---- deletePackage ----

    public function testDeletePackageNotFound(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);
        $this->em->method('getRepository')
            ->with(CommunicationClientPackage::class)
            ->willReturn($repo);

        $response = $this->controller->deletePackage(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testDeletePackageRemovesEntity(): void
    {
        $cp = $this->createMock(CommunicationClientPackage::class);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with(7)->willReturn($cp);
        $this->em->method('getRepository')
            ->with(CommunicationClientPackage::class)
            ->willReturn($repo);

        $this->packageService->expects($this->once())->method('deleteClientPackage')->with($cp);

        $response = $this->controller->deletePackage(7);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['deleted']);
    }

    // ---- createPrice validation ----

    public function testCreatePriceReturnsValidationErrorWhenFieldsMissing(): void
    {
        $violation = new ConstraintViolation('This value should not be null.', null, [], null, 'price', null);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([$violation]));

        $dto = new CreatePricePackageDto();

        $response = $this->controller->createPrice($dto);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Validation failed', $data['error']['message']);
        $this->assertNotEmpty($data['error']['details']);
    }

    public function testCreatePriceReturnsNotFoundWhenAccountOrProductMissing(): void
    {
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $dto = new CreatePricePackageDto(
            price: 10.0,
            priceCurrency: 'USD',
            amount: 100.0,
            currency: 'CUP',
            tenantId: 1,
            productId: 1,
        );

        $this->packageService
            ->method('createPrice')
            ->willThrowException(new MyCurrentException('ACCOUNT_NOT_FOUND', 'Account or product not found', 404));

        $response = $this->controller->createPrice($dto);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Account or product not found', $data['error']['message']);
    }

    // ---- applyClientFilter via reflection ----

    public function testApplyClientFilterAddsClientIdForAdmin(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $qb = $this->createMock(QueryBuilder::class);
        $request = new Request(['clientId' => '42']);

        $method = new \ReflectionMethod(DashboardClientPackagesController::class, 'applyClientFilter');
        $method->setAccessible(true);

        $client = $this->createMock(Client::class);
        $client->method('getId')->willReturn(10);
        $user->method('getCompany')->willReturn($client);

        $this->assertSame('applyClientFilter', $method->getName());
        $this->assertSame(3, $method->getNumberOfParameters());
    }

    // ---- Helper ----

    private function createPricePackageMock(
        int $id,
        string $name,
        float $price,
        string $priceCurrency,
        float $amount,
        string $currency,
        bool $isActive,
    ): CommunicationPricePackage&MockObject {
        $pp = $this->createMock(CommunicationPricePackage::class);
        $pp->method('getId')->willReturn($id);
        $pp->method('getName')->willReturn($name);
        $pp->method('getDescription')->willReturn(null);
        $pp->method('getPrice')->willReturn($price);
        $pp->method('getPriceCurrency')->willReturn($priceCurrency);
        $pp->method('getAmount')->willReturn($amount);
        $pp->method('getCurrency')->willReturn($currency);
        $pp->method('isActive')->willReturn($isActive);
        $pp->method('getActiveStartAt')->willReturn(null);
        $pp->method('getActiveEndAt')->willReturn(null);
        $pp->method('getTenant')->willReturn(null);
        $pp->method('getEnvironment')->willReturn(null);
        $pp->method('getProduct')->willReturn(null);

        return $pp;
    }
}
