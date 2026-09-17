<?php

namespace App\Services\Competency;

use App\Models\CompetencyLevel;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CompetencyLevelService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return CompetencyLevel::query()
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where('level', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        }
                    );
                },
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): CompetencyLevel
    {
        return CompetencyLevel::query()->findOrFail($id);
    }

    public function create(array $data): CompetencyLevel
    {
        return DB::transaction(
            function () use ($data): CompetencyLevel {
                $competencyLevel = CompetencyLevel::query()->create([
                    'level' => $data['level'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'competency_level',
                    target: $competencyLevel,
                    newValues: [
                        'level' => $competencyLevel->level,
                        'name' => $competencyLevel->name,
                        'description' => $competencyLevel->description,
                    ],
                );

                return $competencyLevel;
            }
        );
    }

    public function update(
        CompetencyLevel $competencyLevel,
        array $data,
    ): CompetencyLevel {
        $oldValues = [
            'level' => $competencyLevel->level,
            'name' => $competencyLevel->name,
            'description' => $competencyLevel->description,
        ];

        DB::transaction(
            function () use ($competencyLevel, $data): void {
                $competencyLevel->update($data);
            }
        );

        $competencyLevel->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'competency_level',
            target: $competencyLevel,
            oldValues: $oldValues,
            newValues: [
                'level' => $competencyLevel->level,
                'name' => $competencyLevel->name,
                'description' => $competencyLevel->description,
            ],
        );

        return $competencyLevel;
    }

    public function delete(CompetencyLevel $competencyLevel): void
    {
        $oldValues = [
            'level' => $competencyLevel->level,
            'name' => $competencyLevel->name,
            'description' => $competencyLevel->description,
        ];

        DB::transaction(
            static function () use ($competencyLevel): void {
                $competencyLevel->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'competency_level',
            target: $competencyLevel,
            oldValues: $oldValues,
        );
    }
}
