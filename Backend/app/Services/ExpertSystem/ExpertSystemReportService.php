<?php

namespace App\Services\ExpertSystem;

use App\Models\ExpertConsultation;
use Illuminate\Database\Eloquent\Builder;

class ExpertSystemReportService
{
    public function generate(array $filters): array
    {
        $query = $this->baseQuery();

        $this->applyFilters(
            query: $query,
            filters: $filters,
        );

        $consultations = $query
            ->latest('started_at')
            ->get();

        return [
            'summary' => $this->summary($consultations),
            'by_recommendation' => $this->byRecommendation($consultations),
            'by_consultation_type' => $this->byConsultationType($consultations),
            'consultations' => $this->consultations($consultations),
        ];
    }

    private function baseQuery(): Builder
    {
        return ExpertConsultation::query()
            ->with([
                'employee.department',
                'employee.position',
                'user',
                'result',
            ]);
    }

    private function applyFilters(
        Builder $query,
        array $filters,
    ): void {
        if (!empty($filters['employee_id'])) {
            $query->where(
                'employee_id',
                $filters['employee_id'],
            );
        }

        if (!empty($filters['consultation_type'])) {
            $query->where(
                'consultation_type',
                $filters['consultation_type'],
            );
        }

        if (!empty($filters['recommendation'])) {
            $query->whereHas(
                'result',
                function (Builder $resultQuery) use ($filters): void {
                    $resultQuery->where(
                        'recommendation',
                        $filters['recommendation'],
                    );
                },
            );
        }
    }

    private function summary($consultations): array
    {
        return [
            'total_consultations' => $consultations->count(),

            'completed_consultations' => $consultations
                ->where('status', 'completed')
                ->count(),

            'processing_consultations' => $consultations
                ->where('status', 'processing')
                ->count(),

            'average_score' => $consultations
                ->pluck('result.score')
                ->filter(fn($score) => $score !== null)
                ->avg(),

            'average_confidence' => $consultations
                ->pluck('result.confidence')
                ->filter(fn($confidence) => $confidence !== null)
                ->avg(),
        ];
    }

    private function byRecommendation($consultations): array
    {
        return $consultations
            ->filter(fn($consultation) => $consultation->result !== null)
            ->groupBy(
                fn($consultation) => $consultation->result->recommendation,
            )
            ->map(function ($recommendationConsultations, $recommendation): array {
                return [
                    'recommendation' => $recommendation,
                    'total' => $recommendationConsultations->count(),
                    'average_score' => $recommendationConsultations
                        ->pluck('result.score')
                        ->filter(fn($score) => $score !== null)
                        ->avg(),
                    'average_confidence' => $recommendationConsultations
                        ->pluck('result.confidence')
                        ->filter(fn($confidence) => $confidence !== null)
                        ->avg(),
                ];
            })
            ->values()
            ->all();
    }

    private function byConsultationType($consultations): array
    {
        return $consultations
            ->groupBy('consultation_type')
            ->map(function ($typeConsultations, $consultationType): array {
                return [
                    'consultation_type' => $consultationType,
                    'total' => $typeConsultations->count(),
                    'completed' => $typeConsultations
                        ->where('status', 'completed')
                        ->count(),
                    'processing' => $typeConsultations
                        ->where('status', 'processing')
                        ->count(),
                    'average_score' => $typeConsultations
                        ->pluck('result.score')
                        ->filter(fn($score) => $score !== null)
                        ->avg(),
                ];
            })
            ->values()
            ->all();
    }

    private function consultations($consultations): array
    {
        return $consultations
            ->map(function (ExpertConsultation $consultation): array {
                return [
                    'id' => $consultation->id,

                    'employee' => $consultation->employee ? [
                        'id' => $consultation->employee->id,
                        'employee_number' => $consultation->employee->employee_number,
                        'name' => trim(
                            $consultation->employee->first_name
                                . ' '
                                . $consultation->employee->last_name,
                        ),
                        'department' => $consultation->employee->department ? [
                            'id' => $consultation->employee->department->id,
                            'name' => $consultation->employee->department->name,
                        ] : null,
                        'position' => $consultation->employee->position ? [
                            'id' => $consultation->employee->position->id,
                            'name' => $consultation->employee->position->name,
                        ] : null,
                    ] : null,

                    'consultation_type' => $consultation->consultation_type,
                    'status' => $consultation->status,
                    'started_at' => $consultation->started_at,
                    'completed_at' => $consultation->completed_at,

                    'result' => $consultation->result ? [
                        'id' => $consultation->result->id,
                        'recommendation' => $consultation->result->recommendation,
                        'score' => $consultation->result->score,
                        'confidence' => $consultation->result->confidence,
                        'reason' => $consultation->result->reason,
                        'matched_rules' => $consultation->result->matched_rules,
                        'suggested_actions' => $consultation->result->suggested_actions,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }
}
