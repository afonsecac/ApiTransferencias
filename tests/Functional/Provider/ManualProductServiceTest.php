<?php

namespace App\Tests\Functional\Provider;

use App\DTO\CreateManualProductDto;
use App\Entity\CommunicationProduct;
use App\Exception\MyCurrentException;
use App\Service\Provider\Manual\ManualProductService;

/**
 * @covers \App\Service\Provider\Manual\ManualProductService
 */
class ManualProductServiceTest extends ProviderFunctionalTestCase
{
    private function service(): ManualProductService
    {
        return self::getContainer()->get(ManualProductService::class);
    }

    private function csqDto(array $overrides = [], ?int $environmentId = null): CreateManualProductDto
    {
        return new CreateManualProductDto(
            provider: 'CSQ',
            environmentId: $environmentId,
            values: array_merge([
                'articleId' => '8142',
                'name' => 'Cubacel',
                'fromAmount' => 600.0,
                'toAmount' => 1250.0,
                'step' => 25.0,
                'exchangeRate' => 22.727,
                'destinationUnit' => 'CUP',
                'priceCurrency' => 'USD',
                'topupType' => 'Bundles',
                'requiredIdentifierField' => 'phoneNumber',
                'description' => 'Promo 25GB + Datos Ilimitados',
                'enabled' => true,
            ], $overrides),
        );
    }

    public function testCreatesOneProductPerAmountInTheRange(): void
    {
        $environment = $this->createEnvironment('TEST');

        $result = $this->service()->create($this->csqDto([], $environment->getId()));

        $this->assertSame(27, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertCount(27, $result->products);

        $productRepo = $this->em->getRepository(CommunicationProduct::class);
        $first = $productRepo->findOneBy(['environment' => $environment, 'provider' => 'CSQ', 'externalRef' => '8142-600']);
        $last = $productRepo->findOneBy(['environment' => $environment, 'provider' => 'CSQ', 'externalRef' => '8142-1250']);

        $this->assertNotNull($first);
        $this->assertSame(26.4, $first->getPrice());
        $this->assertSame(600.0, $first->getDestinationAmount());

        $this->assertNotNull($last);
        $this->assertSame(55.0, $last->getPrice());
    }

    public function testReRunningTheSameRangeIsIdempotent(): void
    {
        $environment = $this->createEnvironment('TEST');

        $this->service()->create($this->csqDto([], $environment->getId()));
        $second = $this->service()->create($this->csqDto([], $environment->getId()));

        $this->assertSame(0, $second->created);
        $this->assertSame(27, $second->updated);

        $productRepo = $this->em->getRepository(CommunicationProduct::class);
        $all = $productRepo->findBy(['environment' => $environment, 'provider' => 'CSQ']);
        $this->assertCount(27, $all);
    }

    public function testEtecsaCreatesExactlyOneProduct(): void
    {
        $environment = $this->createEnvironment('TEST');

        $dto = new CreateManualProductDto(
            provider: 'ETECSA',
            environmentId: $environment->getId(),
            values: [
                'packageId' => '4501',
                'packageType' => 'RECARGA',
                'price' => 12.5,
                'description' => 'Recarga 500 CUP',
                'enabled' => true,
            ],
        );

        $result = $this->service()->create($dto);

        $this->assertSame(1, $result->created);
        $this->assertCount(1, $result->products);
        $this->assertSame('4501', $result->products[0]->getExternalRef());
    }

    public function testUnsupportedProviderIsRejected(): void
    {
        $environment = $this->createEnvironment('TEST');

        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(422);

        $this->service()->create(new CreateManualProductDto(
            provider: 'DTONE',
            environmentId: $environment->getId(),
            values: [],
        ));
    }

    public function testUnknownProviderCodeIsRejected(): void
    {
        $environment = $this->createEnvironment('TEST');

        $this->expectException(MyCurrentException::class);

        $this->service()->create(new CreateManualProductDto(
            provider: 'FOO',
            environmentId: $environment->getId(),
            values: [],
        ));
    }

    public function testUnknownEnvironmentIsRejected(): void
    {
        $this->expectException(MyCurrentException::class);
        $this->expectExceptionCode(404);

        $this->service()->create($this->csqDto([], 999999999));
    }
}
