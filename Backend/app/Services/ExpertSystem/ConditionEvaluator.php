<?php

namespace App\Services\ExpertSystem;

final class ConditionEvaluator
{
    public function evaluate(
        mixed $actual,
        string $operator,
        mixed $expected,
    ): bool {
        if (is_numeric($actual) && is_numeric($expected)) {
            return match ($operator) {
                '>' => (float) $actual > (float) $expected,
                '<' => (float) $actual < (float) $expected,
                '>=' => (float) $actual >= (float) $expected,
                '<=' => (float) $actual <= (float) $expected,
                '=' => (float) $actual === (float) $expected,
                default => false,
            };
        }

        return match ($operator) {
            '=' => (string) $actual === (string) $expected,
            default => false,
        };
    }
}
