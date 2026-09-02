<?php

namespace App\Services\Performance;

use App\Models\PerformanceReview;
use Illuminate\Database\Eloquent\Builder;

class PerformanceReportService
{
    public function generate(array $filters): array
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $filters);

        $reviews = $query->get();

        return [
            'summary' => $this->summary($reviews),
            'by_department' => $this->byDepartment($reviews),
            'by_review_type' => $this->byReviewType($reviews),
            'by_period' => $this->byPeriod($reviews),
        ];
    }

    private function baseQuery(): Builder
    {
        return PerformanceReview::query()
            ->with([
                'employee.department',
                'period',
            ])
            ->where('status', 'approved');
    }

    private function applyFilters(
        Builder $query,
        array $filters
    ): void {
        if (!empty($filters['period_id'])) {
            $query->where(
                'performance_period_id',
                $filters['period_id']
            );
        }

        if (!empty($filters['employee_id'])) {
            $query->where(
                'employee_id',
                $filters['employee_id']
            );
        }

        if (!empty($filters['department_id'])) {
            $query->whereHas('employee', function (Builder $employeeQuery) use ($filters) {
                $employeeQuery->where(
                    'department_id',
                    $filters['department_id']
                );
            });
        }

        if (!empty($filters['review_type'])) {
            $query->where(
                'review_type',
                $filters['review_type']
            );
        }
    }

    private function summary($reviews): array
    {
        return [
            'total_reviews' => $reviews->count(),

            'total_employees' => $reviews
                ->pluck('employee_id')
                ->unique()
                ->count(),

            'average_score' => $reviews->avg('overall_score'),

            'highest_score' => $reviews->max('overall_score'),

            'lowest_score' => $reviews->min('overall_score'),
        ];
    }

    private function byDepartment($reviews): array
    {
        return $reviews
            ->groupBy(
                fn($review) => $review->employee?->department_id
            )
            ->map(function ($departmentReviews) {
                $department = $departmentReviews
                    ->first()
                    ?->employee
                    ?->department;

                return [
                    'department_id' => $department?->id,
                    'department_name' => $department?->name,
                    'total_reviews' => $departmentReviews->count(),
                    'total_employees' => $departmentReviews
                        ->pluck('employee_id')
                        ->unique()
                        ->count(),
                    'average_score' => $departmentReviews->avg(
                        'overall_score'
                    ),
                ];
            })
            ->values()
            ->all();
    }

    private function byReviewType($reviews): array
    {
        return $reviews
            ->groupBy('review_type')
            ->map(function ($typeReviews, $reviewType) {
                return [
                    'review_type' => $reviewType,
                    'total_reviews' => $typeReviews->count(),
                    'average_score' => $typeReviews->avg(
                        'overall_score'
                    ),
                ];
            })
            ->values()
            ->all();
    }

    private function byPeriod($reviews): array
    {
        return $reviews
            ->groupBy('performance_period_id')
            ->map(function ($periodReviews) {
                $period = $periodReviews->first()?->period;

                return [
                    'period_id' => $period?->id,
                    'period_name' => $period?->name,
                    'start_date' => $period?->start_date,
                    'end_date' => $period?->end_date,
                    'total_reviews' => $periodReviews->count(),
                    'total_employees' => $periodReviews
                        ->pluck('employee_id')
                        ->unique()
                        ->count(),
                    'average_score' => $periodReviews->avg(
                        'overall_score'
                    ),
                ];
            })
            ->values()
            ->all();
    }
}
