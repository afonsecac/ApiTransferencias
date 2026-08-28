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
}
