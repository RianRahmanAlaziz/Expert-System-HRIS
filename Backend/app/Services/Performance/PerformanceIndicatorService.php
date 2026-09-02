<?php

namespace App\Services\Performance;

use App\Models\PerformanceIndicator;
use Illuminate\Database\Eloquent\Collection;

class PerformanceIndicatorService
{
    public function getAll(): Collection
    {
        return PerformanceIndicator::query()->latest()->get();
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
