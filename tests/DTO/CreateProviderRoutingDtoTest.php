<?php

namespace App\Tests\DTO;

use App\DTO\CreateProviderRoutingDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \App\DTO\CreateProviderRoutingDto
 *
 * Cubre específicamente validateServiceCategory() — el resto de constraints
 * (#[Assert\NotNull], #[Assert\Choice], etc.) ya las ejercita Symfony y no
 * aportan valor repetirlas aquí.
 */
class CreateProviderRoutingDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function dto(?string $serviceName, ?string $subserviceName): CreateProviderRoutingDto
    {
        return new CreateProviderRoutingDto(
            clientId: 1,
            provider: 'DTONE',
            serviceName: $serviceName,
            subserviceName: $subserviceName,
        );
    }

    public function testSubserviceNameWithoutServiceNameIsRejected(): void
    {
        $violations = $this->validator->validate($this->dto(null, 'AIRTIME'));

        $this->assertGreaterThan(0, count($violations));
        $this->assertSame('subserviceName', $violations[0]->getPropertyPath());
    }

    public function testServiceNameWithSubserviceNameIsValid(): void
    {
        $violations = $this->validator->validate($this->dto('Mobile', 'AIRTIME'));

        $this->assertCount(0, $violations);
    }

    public function testServiceNameAloneIsValid(): void
    {
        $violations = $this->validator->validate($this->dto('Mobile', null));

        $this->assertCount(0, $violations);
    }

    public function testBothNullIsValid(): void
    {
        $violations = $this->validator->validate($this->dto(null, null));

        $this->assertCount(0, $violations);
    }

    public function testServiceNameContainingTheSeparatorIsRejected(): void
    {
        $violations = $this->validator->validate($this->dto('Mo|bile', null));

        $this->assertGreaterThan(0, count($violations));
    }

    // ---- COMR-264 (C1): area opcional ----

    public function testAreaAloneWithoutServiceIsValid(): void
    {
        $dto = new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE', areaName: 'Retail');

        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testAreaNameCannotContainThePipeSeparator(): void
    {
        $violations = $this->validator->validate(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE', areaName: 'Gift|Cards'));

        $this->assertGreaterThan(0, count($violations));
        $this->assertSame('areaName', $violations[0]->getPropertyPath());
    }

    public function testAreaNameIsLimitedTo255Characters(): void
    {
        $violations = $this->validator->validate(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE', areaName: str_repeat('a', 256)));

        $this->assertGreaterThan(0, count($violations));
    }

    // ---- COMR-71 (C2): OWN es de mercado, no se puede elegir en el enrutado de comunicaciones ----

    public function testTheOwnMarketProviderCannotBeChosenAsProviderOrFallback(): void
    {
        $asProvider = $this->validator->validate(new CreateProviderRoutingDto(clientId: 1, provider: 'OWN'));
        $asFallback = $this->validator->validate(new CreateProviderRoutingDto(clientId: 1, provider: 'DTONE', fallbackProvider: 'OWN'));

        $this->assertSame('provider', $asProvider[0]->getPropertyPath());
        $this->assertSame('fallbackProvider', $asFallback[0]->getPropertyPath());
    }
}
