<?php

namespace App\Services\ExpertSystem;

final class WeightedScoreCalculator
{
    private const WEIGHTS = [
        ExpertParameter::PERFORMANCE => 0.40,
        ExpertParameter::COMPETENCY => 0.30,
        ExpertParameter::ATTENDANCE => 0.10,
        ExpertParameter::EXPERIENCE => 0.20,
    ];

    public function calculate(array $facts): float
    {
        $experienceScore = min(
            (float) ($facts[ExpertParameter::EXPERIENCE] ?? 0) * 20,
            100,
        );

        $normalized = $facts;
        $normalized[ExpertParameter::EXPERIENCE] =
            $experienceScore;

        $score = 0;

        foreach (self::WEIGHTS as $parameter => $weight) {
            $score += (
                (float) ($normalized[$parameter] ?? 0)
            ) * $weight;
        }

        return round($score, 2);
    }
}
