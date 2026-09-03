<?php

namespace App\Services\Competency;

use App\Models\CompetencyLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CompetencyLevelService
{
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
                return CompetencyLevel::query()->create([
                    'level' => $data['level'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);
            }
        );
    }

    public function update(
        CompetencyLevel $competencyLevel,
        array $data,
    ): CompetencyLevel {
        DB::transaction(
            function () use ($competencyLevel, $data): void {
                $competencyLevel->update($data);
            }
        );

        return $competencyLevel->refresh();
    }

    public function delete(CompetencyLevel $competencyLevel): void
    {
        DB::transaction(
            static function () use ($competencyLevel): void {
                $competencyLevel->delete();
            }
        );
    }
}
