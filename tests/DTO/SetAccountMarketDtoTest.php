<?php

namespace App\Tests\DTO;

use App\DTO\IInput;
use App\DTO\SetAccountMarketDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * @covers \App\DTO\SetAccountMarketDto
 */
class SetAccountMarketDtoTest extends TestCase
{
    public function testItIsAnInputDto(): void
    {
        $this->assertInstanceOf(IInput::class, new SetAccountMarketDto());
    }

    public function testEnabledIsRequired(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new SetAccountMarketDto());

        $this->assertGreaterThan(0, count($violations));
        $this->assertSame('enabled', $violations[0]->getPropertyPath());
    }

    public function testAnExplicitFalseIsValid(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $this->assertCount(0, $validator->validate(new SetAccountMarketDto(enabled: false)));
        $this->assertCount(0, $validator->validate(new SetAccountMarketDto(enabled: true)));
    }
}
