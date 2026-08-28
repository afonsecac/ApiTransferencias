<?php

namespace App\Tests\DTO;

use App\DTO\UpdateProviderRoutingDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \App\DTO\UpdateProviderRoutingDto
 *
 * Cubre validateServiceCategory() con la convención propia de este DTO:
 * null = no tocar, '' = limpiar a comodín — ver su docblock de clase.
 */
class UpdateProviderRoutingDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testSubserviceNameWithoutServiceNameIsRejected(): void
    {
        $dto = new UpdateProviderRoutingDto(subserviceName: 'AIRTIME');

        $violations = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($violations));
        $this->assertSame('subserviceName', $violations[0]->getPropertyPath());
    }

    /**
     * '' (limpiar serviceName) + subserviceName sin tocar (null) es válido
     * a nivel de DTO — el servicio resuelve el "efectivo" y, si queda sin
     * servicio, limpia también el subservicio (ver ProviderRoutingAdminService::update()).
     */
    public function testClearingServiceNameWithSubserviceNameUntouchedIsValidAtDtoLevel(): void
    {
        $dto = new UpdateProviderRoutingDto(serviceName: '');

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testClearingBothIsValid(): void
    {
        $dto = new UpdateProviderRoutingDto(serviceName: '', subserviceName: '');

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testClearingServiceNameWhileSettingSubserviceNameIsRejected(): void
    {
        $dto = new UpdateProviderRoutingDto(serviceName: '', subserviceName: 'AIRTIME');

        $violations = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($violations));
    }

    public function testUntouchedIsValid(): void
    {
        $dto = new UpdateProviderRoutingDto();

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }
}
