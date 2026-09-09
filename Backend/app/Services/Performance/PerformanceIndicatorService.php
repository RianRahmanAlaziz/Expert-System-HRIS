<?php

namespace App\Services\Performance;

use App\Models\PerformanceIndicator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PerformanceIndicatorService
{
    public function paginate(
        int $perPage = 15,
        string $search = '',
        ?string $category = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator {
        return PerformanceIndicator::query()
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                $category !== null,
                fn($query) => $query->where('category', $category),
            )
            ->when(
                $isActive !== null,
                fn($query) => $query->where('is_active', $isActive),
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function getActive(): Collection
    {
        return PerformanceIndicator::query()->where('is_active', true)->latest()->get();
    }

    public function getById(int $id): PerformanceIndicator
    {
        return PerformanceIndicator::query()->withCount('reviewItems')->findOrFail($id);
    }

    public function create(array $data): PerformanceIndicator
    {
        return PerformanceIndicator::create($data);
    }

    public function update(
        PerformanceIndicator $indicator,
        array $data
    ): PerformanceIndicator {
        $indicator->update($data);

        return $indicator->refresh();
    }

    public function delete(PerformanceIndicator $indicator): void
    {
        $indicator->delete();
    }
}
