<?php

namespace App\Services\Career;

use App\Models\CareerPathPosition;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CareerPathPositionService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?int $careerPathId = null,
        ?int $positionId = null,
        ?bool $isEntry = null,
        ?bool $isTarget = null,
    ): LengthAwarePaginator {
        return CareerPathPosition::query()
            ->with([
                'careerPath',
                'position',
            ])
            ->when(
                $careerPathId !== null,
                fn($query) => $query->where(
                    'career_path_id',
                    $careerPathId,
                ),
            )
            ->when(
                $positionId !== null,
                fn($query) => $query->where(
                    'position_id',
                    $positionId,
                ),
            )
            ->when(
                $isEntry !== null,
                fn($query) => $query->where(
                    'is_entry',
                    $isEntry,
                ),
            )
            ->when(
                $isTarget !== null,
                fn($query) => $query->where(
                    'is_target',
                    $isTarget,
                ),
            )
            ->orderBy('career_path_id')
            ->orderBy('sequence')
            ->paginate($perPage);
    }

    public function findById(int $id): CareerPathPosition
    {
        return CareerPathPosition::query()
            ->with([
                'careerPath',
                'position',
            ])
            ->findOrFail($id);
    }

    public function findByCareerPathId(
        int $careerPathId,
    ): Collection {
        return CareerPathPosition::query()
            ->with('position')
            ->where('career_path_id', $careerPathId)
            ->orderBy('sequence')
            ->get();
    }

    public function create(array $data): CareerPathPosition
    {
        return DB::transaction(
            function () use ($data): CareerPathPosition {
                $this->ensureUniquePosition(
                    careerPathId: $data['career_path_id'],
                    positionId: $data['position_id'],
                );

                $careerPathPosition = CareerPathPosition::query()->create([
                    'career_path_id' => $data['career_path_id'],
                    'position_id' => $data['position_id'],
                    'sequence' => $data['sequence'],
                    'is_entry' => $data['is_entry'] ?? false,
                    'is_target' => $data['is_target'] ?? false,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'career_path_position',
                    target: $careerPathPosition,
                    newValues: [
                        'career_path_id' => $careerPathPosition->career_path_id,
                        'position_id' => $careerPathPosition->position_id,
                        'sequence' => $careerPathPosition->sequence,
                        'is_entry' => $careerPathPosition->is_entry,
                        'is_target' => $careerPathPosition->is_target,
                    ],
                );

                return $careerPathPosition->load([
                    'careerPath',
                    'position',
                ]);
            },
        );
    }

    public function update(
        CareerPathPosition $careerPathPosition,
        array $data,
    ): CareerPathPosition {
        return DB::transaction(
            function () use (
                $careerPathPosition,
                $data,
            ): CareerPathPosition {
                $careerPathId = $data['career_path_id']
                    ?? $careerPathPosition->career_path_id;

                $positionId = $data['position_id']
                    ?? $careerPathPosition->position_id;

                $this->ensureUniquePosition(
                    careerPathId: $careerPathId,
                    positionId: $positionId,
                    ignoreId: $careerPathPosition->id,
                );

                $oldValues = [
                    'career_path_id' => $careerPathPosition->career_path_id,
                    'position_id' => $careerPathPosition->position_id,
                    'sequence' => $careerPathPosition->sequence,
                    'is_entry' => $careerPathPosition->is_entry,
                    'is_target' => $careerPathPosition->is_target,
                ];

                $careerPathPosition->update($data);

                $careerPathPosition->refresh();

                $this->activityLogService->log(
                    action: 'update',
                    module: 'career_path_position',
                    target: $careerPathPosition,
                    oldValues: $oldValues,
                    newValues: [
                        'career_path_id' => $careerPathPosition->career_path_id,
                        'position_id' => $careerPathPosition->position_id,
                        'sequence' => $careerPathPosition->sequence,
                        'is_entry' => $careerPathPosition->is_entry,
                        'is_target' => $careerPathPosition->is_target,
                    ],
                );

                return $careerPathPosition->load([
                    'careerPath',
                    'position',
                ]);
            },
        );
    }

    public function delete(
        CareerPathPosition $careerPathPosition,
    ): void {
        DB::transaction(
            function () use ($careerPathPosition): void {
                $oldValues = [
                    'career_path_id' => $careerPathPosition->career_path_id,
                    'position_id' => $careerPathPosition->position_id,
                    'sequence' => $careerPathPosition->sequence,
                    'is_entry' => $careerPathPosition->is_entry,
                    'is_target' => $careerPathPosition->is_target,
                ];

                $careerPathPosition->delete();

                $this->activityLogService->log(
                    action: 'delete',
                    module: 'career_path_position',
                    target: $careerPathPosition,
                    oldValues: $oldValues,
                );
            },
        );
    }

    private function ensureUniquePosition(
        int $careerPathId,
        int $positionId,
        ?int $ignoreId = null,
    ): void {
        $exists = CareerPathPosition::query()
            ->where('career_path_id', $careerPathId)
            ->where('position_id', $positionId)
            ->when(
                $ignoreId !== null,
                fn($query) => $query->where(
                    'id',
                    '!=',
                    $ignoreId,
                ),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'position_id' => [
                    'This position already exists in the selected career path.',
                ],
            ]);
        }
    }
}
