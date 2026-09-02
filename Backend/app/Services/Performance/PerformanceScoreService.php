<?php

namespace App\Services\Performance;

use App\Models\PerformanceReview;
use InvalidArgumentException;

class PerformanceScoreService
{
    /**
     * Calculate the overall score of a performance review.
     *
     * Formula:
     * Overall Score = Σ (Score × Weight / 100)
     */
    public function calculate(PerformanceReview $review): float
    {
        $review->loadMissing('items.indicator');

        if ($review->items->isEmpty()) {
            throw new InvalidArgumentException('Performance review belum memiliki indikator.');
        }

        $totalWeight = $review->items->sum(
            fn($item) => (float) $item->indicator->weight
        );

        if (round($totalWeight, 2) !== 100.00) {
            throw new InvalidArgumentException('Total bobot indikator performance harus 100%.');
        }

        $overallScore = $review->items->sum(
            function ($item) {
                $score = (float) ($item->score ?? 0);
                $weight = (float) $item->indicator->weight;

                return ($score * $weight) / 100;
            }
        );

        return round($overallScore, 2);
    }

    /**
     * Calculate and save the overall score.
     */
    public function calculateAndSave(PerformanceReview $review): PerformanceReview
    {
        $review->overall_score = $this->calculate($review);
        $review->save();

        return $review->refresh();
    }
}
