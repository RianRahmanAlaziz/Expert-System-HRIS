<?php

namespace App\Services\Training;

use App\Models\Training;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TrainingService
{
    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return Training::query()
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
            ->latest()->paginate($perPage);
    }

    public function findById(int $id): Training
    {
        return Training::query()->findOrFail($id);
    }

    public function create(array $data): Training
    {
        return DB::transaction(
            function () use ($data): Training {
                return Training::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'category' => $data['category'] ?? null,
                    'description' => $data['description'] ?? null,
                    'trainer' => $data['trainer'] ?? null,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'capacity' => $data['capacity'] ?? null,
                    'status' => $data['status'],
                ]);
            }
        );
    }

    public function update(
        Training $training,
        array $data,
    ): Training {
        DB::transaction(
            function () use ($training, $data): void {
                $training->update($data);
            }
        );

        return $training->refresh();
    }

    public function updateStatus(
        Training $training,
        string $status,
    ): Training {
        DB::transaction(
            function () use ($training, $status): void {
                $training->update([
                    'status' => $status,
                ]);
            }
        );

        return $training->refresh();
    }

    public function delete(Training $training): void
    {
        DB::transaction(
            static function () use ($training): void {
                $training->delete();
            }
        );
    }
}
