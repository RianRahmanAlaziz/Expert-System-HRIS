<?php

namespace App\Services\Position;


use App\Models\PositionRequirementCompetency;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PositionRequirementCompetencyService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?int $positionRequirementId = null,
        ?int $competencyId = null,
        ?bool $isRequired = null,
    ): LengthAwarePaginator {
        return PositionRequirementCompetency::query()
            ->with([
                'positionRequirement.position',
                'competency',
                'requiredLevel',
            ])
            ->when(
                $positionRequirementId !== null,
                fn($query) => $query->where(
                    'position_requirement_id',
                    $positionRequirementId
                )
            )
            ->when(
                $competencyId !== null,
                fn($query) => $query->where(
                    'competency_id',
                    $competencyId
                )
            )
            ->when(
                $isRequired !== null,
                fn($query) => $query->where(
                    'is_required',
                    $isRequired
                )
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(
        int $id
    ): PositionRequirementCompetency {
        return PositionRequirementCompetency::query()
            ->with([
                'positionRequirement.position',
                'competency',
                'requiredLevel',
            ])
            ->findOrFail($id);
    }

    public function getByPositionRequirement(
        int $positionRequirementId
    ): array {
        return PositionRequirementCompetency::query()
            ->with([
                'competency',
                'requiredLevel',
            ])
            ->where(
                'position_requirement_id',
                $positionRequirementId
            )
            ->orderByDesc('is_required')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function create(
        array $data
    ): PositionRequirementCompetency {
        return DB::transaction(
            function () use ($data): PositionRequirementCompetency {
                $this->ensureUnique(
                    $data['position_requirement_id'],
                    $data['competency_id']
                );

                $item = PositionRequirementCompetency::query()->create([
                    'position_requirement_id' => $data['position_requirement_id'],
                    'competency_id' => $data['competency_id'],
                    'required_level_id' => $data['required_level_id'] ?? null,
                    'minimum_score' => $data['minimum_score'] ?? null,
                    'weight' => $data['weight'] ?? 0,
                    'is_required' => $data['is_required'] ?? true,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'position_requirement_competency',
                    target: $item,
                    newValues: [
                        'position_requirement_id' => $item->position_requirement_id,
                        'competency_id' => $item->competency_id,
                        'required_level_id' => $item->required_level_id,
                        'minimum_score' => $item->minimum_score,
                        'weight' => $item->weight,
                        'is_required' => $item->is_required,
                    ],
                );

                return $item->load([
                    'positionRequirement.position',
                    'competency',
                    'requiredLevel',
                ]);
            }
        );
    }

    public function update(
        PositionRequirementCompetency $item,
        array $data
    ): PositionRequirementCompetency {
        return DB::transaction(
            function () use ($item, $data): PositionRequirementCompetency {
                $positionRequirementId = $data['position_requirement_id']
                    ?? $item->position_requirement_id;

                $competencyId = $data['competency_id']
                    ?? $item->competency_id;

                $this->ensureUnique(
                    $positionRequirementId,
                    $competencyId,
                    $item->id
                );

                $oldValues = [
                    'position_requirement_id' => $item->position_requirement_id,
                    'competency_id' => $item->competency_id,
                    'required_level_id' => $item->required_level_id,
                    'minimum_score' => $item->minimum_score,
                    'weight' => $item->weight,
                    'is_required' => $item->is_required,
                ];

                $item->update($data);

                $item->refresh();

                $this->activityLogService->log(
                    action: 'update',
                    module: 'position_requirement_competency',
                    target: $item,
                    oldValues: $oldValues,
                    newValues: [
                        'position_requirement_id' => $item->position_requirement_id,
                        'competency_id' => $item->competency_id,
                        'required_level_id' => $item->required_level_id,
                        'minimum_score' => $item->minimum_score,
                        'weight' => $item->weight,
                        'is_required' => $item->is_required,
                    ],
                );

                return $item->load([
                    'positionRequirement.position',
                    'competency',
                    'requiredLevel',
                ]);
            }
        );
    }

    public function delete(
        PositionRequirementCompetency $item
    ): void {
        DB::transaction(
            function () use ($item): void {
                $oldValues = [
                    'position_requirement_id' => $item->position_requirement_id,
                    'competency_id' => $item->competency_id,
                    'required_level_id' => $item->required_level_id,
                    'minimum_score' => $item->minimum_score,
                    'weight' => $item->weight,
                    'is_required' => $item->is_required,
                ];

                $item->delete();

                $this->activityLogService->log(
                    action: 'delete',
                    module: 'position_requirement_competency',
                    target: $item,
                    oldValues: $oldValues,
                );
            }
        );
    }

    private function ensureUnique(
        int $positionRequirementId,
        int $competencyId,
        ?int $ignoreId = null
    ): void {
        $exists = PositionRequirementCompetency::query()
            ->where(
                'position_requirement_id',
                $positionRequirementId
            )
            ->where('competency_id', $competencyId)
            ->when(
                $ignoreId !== null,
                fn($query) => $query->whereKeyNot($ignoreId)
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'competency_id' => [
                    'This competency already exists '
                        . 'for the selected position requirement.',
                ],
            ]);
        }
    }
}
