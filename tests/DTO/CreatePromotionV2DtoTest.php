<?php

namespace App\Tests\DTO;

use App\DTO\CreatePromotionV2Dto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \App\DTO\CreatePromotionV2Dto
 *
 * Primer test de DTO en el repo (no existía tests/DTO/) — cubre
 * específicamente validateBenefitsSchedule(), la única lógica de
 * validación nueva; el resto de constraints (#[Assert\NotBlank] etc.) ya
 * las ejercita Symfony y no aportan valor repetirlas aquí.
 */
class CreatePromotionV2DtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function dto(?array $benefits): CreatePromotionV2Dto
    {
        return new CreatePromotionV2Dto(
            name: 'Promo',
            description: 'Promo',
            packageNameTemplate: 'Promo {monto}',
            packageDescriptionTemplate: 'Promo {monto}',
            startAt: '2026-08-18T00:00:00+00:00',
            endAt: '2026-08-25T00:00:00+00:00',
            environmentId: 1,
            destinationCurrency: 'CUP',
            amountFrom: 500.0,
            amountTo: 500.0,
            amountStep: 1.0,
            benefits: $benefits,
        );
    }

    private function scheduleViolations(?array $benefits): array
    {
        $violations = $this->validator->validate($this->dto($benefits));

        $result = [];
        foreach ($violations as $v) {
            if (str_contains($v->getPropertyPath(), 'schedule')) {
                $result[] = $v->getMessage();
            }
        }

        return $result;
    }

    public function testNoBenefitsIsValid(): void
    {
        $this->assertSame([], $this->scheduleViolations(null));
    }

    public function testBenefitWithoutScheduleKeyIsValid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA']];
        $this->assertSame([], $this->scheduleViolations($benefits));
    }

    public function testScheduleWithBothNullMeans24hAndIsValid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => null, 'end' => null]]];
        $this->assertSame([], $this->scheduleViolations($benefits));
    }

    public function testScheduleWithBothValidTimesIsValid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => '01:00', 'end' => '06:00']]];
        $this->assertSame([], $this->scheduleViolations($benefits));
    }

    public function testScheduleWithOnlyStartSetIsInvalid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => '01:00', 'end' => null]]];
        $this->assertNotEmpty($this->scheduleViolations($benefits));
    }

    public function testScheduleWithMalformedTimeIsInvalid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => '1am', 'end' => '06:00']]];
        $this->assertNotEmpty($this->scheduleViolations($benefits));
    }

    public function testScheduleWithOutOfRangeTimeIsInvalid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => '25:00', 'end' => '06:00']]];
        $this->assertNotEmpty($this->scheduleViolations($benefits));
    }

    public function testScheduleWithEqualStartAndEndIsInvalid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => '01:00', 'end' => '01:00']]];
        $this->assertNotEmpty($this->scheduleViolations($benefits));
    }

    public function testScheduleMissingEndKeyEntirelyIsInvalid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => '01:00']]];
        $this->assertNotEmpty($this->scheduleViolations($benefits));
    }

    private function operationViolations(?array $benefits): array
    {
        $violations = $this->validator->validate($this->dto($benefits));

        $result = [];
        foreach ($violations as $v) {
            if (str_contains($v->getPropertyPath(), 'operation') || str_contains($v->getPropertyPath(), 'value')) {
                $result[] = $v->getMessage();
            }
        }

        return $result;
    }

    public function testBenefitWithoutOperationIsValid(): void
    {
        $benefits = [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'amount' => ['base' => 600, 'promotion_bonus' => 0]]];
        $this->assertSame([], $this->operationViolations($benefits));
    }

    public function testBenefitWithOperationAndNumericValueIsValid(): void
    {
        $benefits = [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'operation' => 'MULTIPLY', 'value' => 6]];
        $this->assertSame([], $this->operationViolations($benefits));
    }

    public function testBenefitWithUnknownOperationIsInvalid(): void
    {
        $benefits = [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'operation' => 'DIVIDE', 'value' => 6]];
        $this->assertNotEmpty($this->operationViolations($benefits));
    }

    public function testBenefitWithOperationButMissingValueIsInvalid(): void
    {
        $benefits = [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'operation' => 'ADD']];
        $this->assertNotEmpty($this->operationViolations($benefits));
    }

    public function testBenefitWithOperationAndNonNumericValueIsInvalid(): void
    {
        $benefits = [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'operation' => 'SET', 'value' => 'ilimitado']];
        $this->assertNotEmpty($this->operationViolations($benefits));
    }
}
