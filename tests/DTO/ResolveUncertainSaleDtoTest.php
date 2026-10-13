<?php

namespace App\Tests\DTO;

use App\DTO\IInput;
use App\DTO\ResolveUncertainSaleDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * @covers \App\DTO\ResolveUncertainSaleDto
 */
class ResolveUncertainSaleDtoTest extends TestCase
{
    private function violations(ResolveUncertainSaleDto $dto): array
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        return array_map(static fn ($v): string => $v->getPropertyPath(), iterator_to_array($validator->validate($dto)));
    }

    public function testIsAnInputDto(): void
    {
        $this->assertInstanceOf(IInput::class, new ResolveUncertainSaleDto());
    }

    public function testAValidResolutionHasNoViolations(): void
    {
        $this->assertSame([], $this->violations(new ResolveUncertainSaleDto('completed', 'El proveedor la confirmó')));
        $this->assertSame([], $this->violations(new ResolveUncertainSaleDto('rejected', 'El proveedor no la conoce')));
    }

    public function testResolutionAndReasonAreRequired(): void
    {
        $this->assertEqualsCanonicalizing(['resolution', 'reason'], $this->violations(new ResolveUncertainSaleDto()));
    }

    public function testResolutionMustBeCompletedOrRejected(): void
    {
        $this->assertSame(['resolution'], $this->violations(new ResolveUncertainSaleDto('maybe', 'Motivo suficiente')));
    }

    public function testReasonNeedsAtLeastFiveCharacters(): void
    {
        $this->assertSame(['reason'], $this->violations(new ResolveUncertainSaleDto('completed', 'ok')));
    }
}
