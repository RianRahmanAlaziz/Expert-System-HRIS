<?php

namespace App\Services\Competency;

use App\Models\Competency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CompetencyService
{
    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return Competency::query()
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('category', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        }
                    );
                },
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): Competency
    {
        return Competency::query()->findOrFail($id);
    }

    public function create(array $data): Competency
    {
        return DB::transaction(
            function () use ($data): Competency {
                return Competency::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                ]);
            }
        );
    }

    public function update(
        Competency $competency,
        array $data,
    ): Competency {
        DB::transaction(
            function () use ($competency, $data): void {
                $competency->update($data);
            }
        );

        return $competency->refresh();
    }

    public function delete(Competency $competency): void
    {
        DB::transaction(
            static function () use ($competency): void {
                $competency->delete();
            }
        );
    }
}
