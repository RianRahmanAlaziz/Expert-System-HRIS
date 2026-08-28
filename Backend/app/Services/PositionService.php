<?php

namespace App\Services;

use App\Models\Position;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PositionService
{
    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return Position::query()
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        }
                    );
                },
            )->latest()->paginate($perPage);
    }

    public function findById(int $id): Position
    {
        return Position::query()->findOrFail($id);
    }

    public function create(array $data): Position
    {
        return DB::transaction(
            function () use ($data): Position {
                return Position::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'level' => $data['level'],
                    'status' => $data['status'],
                    'is_active' => $data['is_active'] ?? true,
                ]);
            }
        );
    }


    public function update(
        Position $position,
        array $data,
    ): Position {
        DB::transaction(
            function () use ($position, $data): void {
                $position->update($data);
            }
        );

        return $position->refresh();
    }

    public function delete(Position $position): void
    {
        DB::transaction(
            static function () use ($position): void {
                $position->delete();
            }
        );
    }
}
