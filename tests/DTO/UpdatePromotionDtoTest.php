<?php

namespace App\Tests\DTO;

use App\DTO\UpdatePromotionDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @covers \App\DTO\UpdatePromotionDto
 *
 * Cubre `benefits` (solo aplicable a promociones V2 — ver
 * CommunicationPromotionService::updatePackageBenefits()), que reutiliza
 * los mismos validadores compartidos que CreatePromotionV2Dto
 * (BenefitScheduleValidator/BenefitOperationValidator, ver
 * CreatePromotionV2DtoTest para la cobertura exhaustiva de esos
 * validadores) — aquí solo se confirma que quedaron cableados.
 */
class UpdatePromotionDtoTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    private function violationMessages(?array $benefits): array
    {
        $dto = new UpdatePromotionDto(benefits: $benefits);
        $violations = $this->validator->validate($dto);

        $result = [];
        foreach ($violations as $v) {
            $result[] = $v->getMessage();
        }

        return $result;
    }

    public function testTermsAloneWithoutBenefitsStaysValid(): void
    {
        $dto = new UpdatePromotionDto(terms: [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY']]);
        $this->assertCount(0, $this->validator->validate($dto));
    }

    public function testNoBenefitsIsValid(): void
    {
        $this->assertSame([], $this->violationMessages(null));
    }

    public function testBenefitWithValidScheduleIsValid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => '01:00', 'end' => '06:00']]];
        $this->assertSame([], $this->violationMessages($benefits));
    }

    public function testBenefitWithMismatchedScheduleTimesIsInvalid(): void
    {
        $benefits = [['type' => 'DATA', 'unit' => 'ILIM', 'unit_type' => 'DATA', 'schedule' => ['start' => '01:00', 'end' => null]]];
        $this->assertNotEmpty($this->violationMessages($benefits));
    }

    public function testBenefitWithOperationAndNumericValueIsValid(): void
    {
        $benefits = [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'operation' => 'MULTIPLY', 'value' => 6]];
        $this->assertSame([], $this->violationMessages($benefits));
    }

    public function testBenefitWithOperationButMissingValueIsInvalid(): void
    {
        $benefits = [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY', 'operation' => 'MULTIPLY']];
        $this->assertNotEmpty($this->violationMessages($benefits));
    }

    public function testGetBenefitsReturnsWhatWasSet(): void
    {
        $benefits = [['type' => 'CREDITS', 'unit' => 'CUP', 'unit_type' => 'CURRENCY']];
        $dto = new UpdatePromotionDto(benefits: $benefits);
        $this->assertSame($benefits, $dto->getBenefits());
    }
}
