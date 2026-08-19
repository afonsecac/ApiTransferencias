<?php

namespace App\DTO\Validation;

use App\Enums\BenefitOperationEnum;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Validación compartida de `benefits[].operation`/`benefits[].value` —
 * usada desde CreatePromotionV2Dto y CreateCommunicationPackageBatchDto
 * (mismo shape de `benefits`, ver CommunicationPackage::$benefits). `value`
 * solo es obligatorio cuando se especifica `operation`; sin `operation`,
 * `amount.base`/`amount.promotion_bonus` se usan tal cual (comportamiento
 * previo, sin cambios).
 */
final class BenefitOperationValidator
{
    public static function validate(?array $benefits, ExecutionContextInterface $context): void
    {
        if ($benefits === null) {
            return;
        }

        foreach ($benefits as $index => $benefit) {
            if (!is_array($benefit) || !array_key_exists('operation', $benefit) || $benefit['operation'] === null) {
                continue;
            }

            $path = "benefits[{$index}]";
            $operation = $benefit['operation'];

            if (!is_string($operation) || BenefitOperationEnum::tryFrom($operation) === null) {
                $allowed = implode(', ', array_column(BenefitOperationEnum::cases(), 'value'));
                $context->buildViolation("operation debe ser uno de: {$allowed}")
                    ->atPath("{$path}.operation")
                    ->addViolation();
                continue;
            }

            $value = $benefit['value'] ?? null;
            if (!is_int($value) && !is_float($value)) {
                $context->buildViolation('value es obligatorio y numérico cuando se especifica operation')
                    ->atPath("{$path}.value")
                    ->addViolation();
            }
        }
    }
}
