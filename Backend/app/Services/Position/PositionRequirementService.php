<?php

namespace App\Services\Position;

use App\Models\PositionRequirement;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PositionRequirementService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $positionId = null,
    ): LengthAwarePaginator {
        return PositionRequirement::query()
            ->with('position')
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->whereHas(
                        'position',
                        function ($query) use ($search): void {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        },
                    );
                },
            )
            ->when(
                $positionId !== null,
                function ($query) use ($positionId): void {
                    $query->where('position_id', $positionId);
                },
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): PositionRequirement
    {
        return PositionRequirement::query()
            ->with('position')
            ->findOrFail($id);
    }

    public function findByPositionId(int $positionId): ?PositionRequirement
    {
        return PositionRequirement::query()
            ->with('position')
            ->where('position_id', $positionId)
            ->where('is_active', true)
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    public function create(array $data): PositionRequirement
    {
        return DB::transaction(
            function () use ($data): PositionRequirement {
                $requirement = PositionRequirement::query()->create([
                    'position_id' => $data['position_id'],
                    'minimum_experience_years' => $data['minimum_experience_years'],
                    'minimum_performance_score' => $data['minimum_performance_score'] ?? null,
                    'minimum_attendance_percentage' => $data['minimum_attendance_percentage'] ?? null,
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                    'is_active' => $data['is_active'] ?? true,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'position_requirement',
                    target: $requirement,
                    newValues: [
                        'position_id' => $requirement->position_id,
                        'minimum_experience_years' => $requirement->minimum_experience_years,
                        'minimum_performance_score' => $requirement->minimum_performance_score,
                        'minimum_attendance_percentage' => $requirement->minimum_attendance_percentage,
                        'description' => $requirement->description,
                        'status' => $requirement->status,
                        'is_active' => $requirement->is_active,
                    ],
                );

                return $requirement->load('position');
            },
        );
    }

    public function update(
        PositionRequirement $positionRequirement,
        array $data,
    ): PositionRequirement {
        $oldValues = [
            'position_id' => $positionRequirement->position_id,
            'minimum_experience_years' => $positionRequirement->minimum_experience_years,
            'minimum_performance_score' => $positionRequirement->minimum_performance_score,
            'minimum_attendance_percentage' => $positionRequirement->minimum_attendance_percentage,
            'description' => $positionRequirement->description,
            'status' => $positionRequirement->status,
            'is_active' => $positionRequirement->is_active,
        ];

        DB::transaction(
            function () use ($positionRequirement, $data): void {
                $positionRequirement->update($data);
            }
        );

        $positionRequirement
            ->refresh()
            ->load('position');

        $this->activityLogService->log(
            action: 'update',
            module: 'position_requirement',
            target: $positionRequirement,
            oldValues: $oldValues,
            newValues: [
                'position_id' => $positionRequirement->position_id,
                'minimum_experience_years' => $positionRequirement->minimum_experience_years,
                'minimum_performance_score' => $positionRequirement->minimum_performance_score,
                'minimum_attendance_percentage' => $positionRequirement->minimum_attendance_percentage,
                'description' => $positionRequirement->description,
                'status' => $positionRequirement->status,
                'is_active' => $positionRequirement->is_active,
            ],
        );

        return $positionRequirement;
    }

    public function delete(PositionRequirement $positionRequirement): void
    {
        $oldValues = [
            'position_id' => $positionRequirement->position_id,
            'minimum_experience_years' => $positionRequirement->minimum_experience_years,
            'minimum_performance_score' => $positionRequirement->minimum_performance_score,
            'minimum_attendance_percentage' => $positionRequirement->minimum_attendance_percentage,
            'description' => $positionRequirement->description,
            'status' => $positionRequirement->status,
            'is_active' => $positionRequirement->is_active,
        ];

        DB::transaction(
            function () use ($positionRequirement): void {
                $positionRequirement->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'position_requirement',
            target: $positionRequirement,
            oldValues: $oldValues,
        );
    }
}
