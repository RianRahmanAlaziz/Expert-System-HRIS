<?php

namespace App\Services\Promotion;

use App\Models\PromotionAssessment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PromotionAssessmentService
{
    public function paginate(
        int $perPage = 15,
        ?int $employeeId = null,
        ?int $currentPositionId = null,
        ?int $targetPositionId = null,
        ?int $assessedBy = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        return PromotionAssessment::query()
            ->with([
                'employee',
                'currentPosition',
                'targetPosition',
                'assessedBy',
            ])
            ->when(
                $employeeId !== null,
                fn($query) => $query->where(
                    'employee_id',
                    $employeeId,
                ),
            )
            ->when(
                $currentPositionId !== null,
                fn($query) => $query->where(
                    'current_position_id',
                    $currentPositionId,
                ),
            )
            ->when(
                $targetPositionId !== null,
                fn($query) => $query->where(
                    'target_position_id',
                    $targetPositionId,
                ),
            )
            ->when(
                $assessedBy !== null,
                fn($query) => $query->where(
                    'assessed_by',
                    $assessedBy,
                ),
            )
            ->when(
                $status !== null,
                fn($query) => $query->where('status', $status),
            )
            ->latest('assessment_date')
            ->paginate($perPage);
    }

    public function findById(int $id): PromotionAssessment
    {
        return PromotionAssessment::query()
            ->with([
                'employee',
                'currentPosition',
                'targetPosition',
                'assessedBy',
                'items',
            ])
            ->findOrFail($id);
    }

    public function create(
        User $user,
        array $data,
    ): PromotionAssessment {
        return DB::transaction(
            function () use ($user, $data): PromotionAssessment {
                $assessment = PromotionAssessment::query()->create([
                    'employee_id' => $data['employee_id'],
                    'current_position_id' => $data['current_position_id'],
                    'target_position_id' => $data['target_position_id'],
                    'assessed_by' => $user->id,
                    'assessment_date' => $data['assessment_date'],
                    'status' => $data['status'],
                    'overall_score' => $data['overall_score'] ?? null,
                    'recommendation' => $data['recommendation'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                return $assessment->load([
                    'employee',
                    'currentPosition',
                    'targetPosition',
                    'assessedBy',
                    'items',
                ]);
            },
        );
    }

    public function update(
        PromotionAssessment $promotionAssessment,
        array $data,
    ): PromotionAssessment {
        DB::transaction(
            function () use (
                $promotionAssessment,
                $data,
            ): void {
                $promotionAssessment->update($data);
            },
        );

        return $promotionAssessment
            ->refresh()
            ->load([
                'employee',
                'currentPosition',
                'targetPosition',
                'assessedBy',
                'items',
            ]);
    }

    public function delete(
        PromotionAssessment $promotionAssessment,
    ): void {
        DB::transaction(
            static function () use ($promotionAssessment): void {
                $promotionAssessment->delete();
            },
        );
    }
}
