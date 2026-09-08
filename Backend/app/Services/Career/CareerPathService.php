<?php

namespace App\Services\Career;

use App\Models\CareerPath;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CareerPathService
{
    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator {
        return CareerPath::query()
            ->when(
                $search !== null,
                fn($query) => $query->where(function ($query) use ($search): void {
                    $query
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                }),
            )
            ->when(
                $status !== null,
                fn($query) => $query->where('status', $status),
            )
            ->when(
                $isActive !== null,
                fn($query) => $query->where('is_active', $isActive),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): CareerPath
    {
        return CareerPath::query()
            ->findOrFail($id);
    }

    public function create(array $data): CareerPath
    {
        return DB::transaction(
            function () use ($data): CareerPath {
                return CareerPath::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                    'is_active' => $data['is_active'] ?? true,
                ]);
            },
        );
    }

    public function update(
        CareerPath $careerPath,
        array $data,
    ): CareerPath {
        DB::transaction(
            function () use ($careerPath, $data): void {
                $careerPath->update($data);
            },
        );

        return $careerPath->refresh();
    }

    public function delete(CareerPath $careerPath): void
    {
        DB::transaction(
            static function () use ($careerPath): void {
                $careerPath->delete();
            },
        );
    }
}
