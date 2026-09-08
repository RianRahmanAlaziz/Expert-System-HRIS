<?php

namespace App\Services\Position;

use App\Models\PositionRequirement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PositionRequirementService
{
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
            ->latest()
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

                return $requirement->load('position');
            },
        );
    }

    public function update(
        PositionRequirement $positionRequirement,
        array $data,
    ): PositionRequirement {
        DB::transaction(
            function () use ($positionRequirement, $data): void {
                $positionRequirement->update($data);
            }
        );

        return $positionRequirement
            ->refresh()
            ->load('position');
    }

    public function delete(PositionRequirement $positionRequirement): void
    {
        DB::transaction(
            static function () use ($positionRequirement): void {
                $positionRequirement->delete();
            }
        );
    }
}
