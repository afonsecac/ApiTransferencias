<?php

namespace App\DTO\Validation;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Validación compartida de `benefits[].schedule` — usada desde
 * CreatePromotionV2Dto y UpdatePromotionDto (mismo shape de `benefits`, ver
 * CommunicationPackage::$benefits). `schedule` es opcional y puramente
 * informativo — no se valida contra el `type`/`unit` del beneficio, solo su
 * propia forma: ambos extremos null (24h) o ambos presentes en formato
 * HH:mm y distintos entre sí (una franja de largo cero no tiene sentido).
 */
final class BenefitScheduleValidator
{
    public static function validate(?array $benefits, ExecutionContextInterface $context): void
    {
        if ($benefits === null) {
            return;
        }

        foreach ($benefits as $index => $benefit) {
            if (!is_array($benefit) || !array_key_exists('schedule', $benefit) || $benefit['schedule'] === null) {
                continue;
            }

            $schedule = $benefit['schedule'];
            $path = "benefits[{$index}].schedule";

            if (!is_array($schedule) || !array_key_exists('start', $schedule) || !array_key_exists('end', $schedule)) {
                $context->buildViolation('schedule debe tener start y end (ambos null, o ambos "HH:mm")')
                    ->atPath($path)
                    ->addViolation();
                continue;
            }

            [$start, $end] = [$schedule['start'], $schedule['end']];
            if ($start === null && $end === null) {
                continue;
            }

            $timeFormat = '/^([01]\d|2[0-3]):[0-5]\d$/';
            if (!is_string($start) || !is_string($end) || !preg_match($timeFormat, $start) || !preg_match($timeFormat, $end)) {
                $context->buildViolation('schedule.start/end deben ser ambos null (24h) o ambos "HH:mm"')
                    ->atPath($path)
                    ->addViolation();
                continue;
            }

            if ($start === $end) {
                $context->buildViolation('schedule.start y schedule.end no pueden ser iguales')
                    ->atPath($path)
                    ->addViolation();
            }
        }
    }
}
