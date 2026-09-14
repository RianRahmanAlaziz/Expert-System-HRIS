<?php

namespace App\Services\Recommendation;

use App\Models\Recommendation;
use Illuminate\Database\Eloquent\Builder;

class RecommendationReportService
{
    public function generate(array $filters): array
    {
        $query = $this->baseQuery();

        $this->applyFilters(
            query: $query,
            filters: $filters,
        );

        $recommendations = $query
            ->latest('recommended_at')
            ->latest('id')
            ->get();

        return [
            'summary' => $this->summary($recommendations),
            'by_type' => $this->byType($recommendations),
            'by_status' => $this->byStatus($recommendations),
            'by_priority' => $this->byPriority($recommendations),
            'recommendations' => $this->recommendations($recommendations),
        ];
    }

    private function baseQuery(): Builder
    {
        return Recommendation::query()
            ->with([
                'employee.department',
                'employee.position',
                'expertConsultation',
                'histories.user',
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

        if (!empty($filters['type'])) {
            $query->where(
                'type',
                $filters['type'],
            );
        }

        if (!empty($filters['status'])) {
            $query->where(
                'status',
                $filters['status'],
            );
        }

        if (!empty($filters['priority'])) {
            $query->where(
                'priority',
                $filters['priority'],
            );
        }
    }

    private function summary($recommendations): array
    {
        return [
            'total_recommendations' => $recommendations->count(),

            'pending_recommendations' => $recommendations
                ->where('status', 'pending')
                ->count(),

            'approved_recommendations' => $recommendations
                ->where('status', 'approved')
                ->count(),

            'rejected_recommendations' => $recommendations
                ->where('status', 'rejected')
                ->count(),

            'implemented_recommendations' => $recommendations
                ->where('status', 'implemented')
                ->count(),
        ];
    }

    private function byType($recommendations): array
    {
        return $recommendations
            ->groupBy('type')
            ->map(function ($items, $type): array {
                return [
                    'type' => $type,
                    'total' => $items->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function byStatus($recommendations): array
    {
        return $recommendations
            ->groupBy('status')
            ->map(function ($items, $status): array {
                return [
                    'status' => $status,
                    'total' => $items->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function byPriority($recommendations): array
    {
        return $recommendations
            ->groupBy('priority')
            ->map(function ($items, $priority): array {
                return [
                    'priority' => $priority,
                    'total' => $items->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function recommendations($recommendations): array
    {
        return $recommendations
            ->map(function (Recommendation $recommendation): array {
                return [
                    'id' => $recommendation->id,

                    'employee' => $recommendation->employee ? [
                        'id' => $recommendation->employee->id,
                        'employee_number' => $recommendation->employee->employee_number,
                        'name' => trim(
                            $recommendation->employee->first_name
                                . ' '
                                . $recommendation->employee->last_name,
                        ),
                        'department' => $recommendation->employee->department ? [
                            'id' => $recommendation->employee->department->id,
                            'name' => $recommendation->employee->department->name,
                        ] : null,
                        'position' => $recommendation->employee->position ? [
                            'id' => $recommendation->employee->position->id,
                            'name' => $recommendation->employee->position->name,
                        ] : null,
                    ] : null,

                    'expert_consultation_id' =>   $recommendation->expert_consultation_id,

                    'type' => $recommendation->type,
                    'title' => $recommendation->title,
                    'description' => $recommendation->description,
                    'priority' => $recommendation->priority,
                    'status' => $recommendation->status,
                    'recommended_at' => $recommendation->recommended_at,

                    'histories' => $recommendation->histories
                        ->map(fn($history): array => [
                            'id' => $history->id,
                            'user_id' => $history->user_id,
                            'old_status' => $history->old_status,
                            'new_status' => $history->new_status,
                            'notes' => $history->notes,
                            'created_at' => $history->created_at,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
