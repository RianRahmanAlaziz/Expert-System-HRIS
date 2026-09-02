<?php

namespace App\Services\Performance;

use App\Models\PerformancePeriod;
use Illuminate\Database\Eloquent\Collection;

class PerformancePeriodService
{
    public function getAll(): Collection
    {
        return PerformancePeriod::query()->latest('start_date')->get();
    }

    public function getById(int $id): PerformancePeriod
    {
        return PerformancePeriod::query()->withCount('reviews')->findOrFail($id);
    }

    public function create(array $data): PerformancePeriod
    {
        return PerformancePeriod::create($data);
    }

    public function update(
        PerformancePeriod $period,
        array $data
    ): PerformancePeriod {
        $period->update($data);

        return $period->refresh();
    }

    public function delete(PerformancePeriod $period): void
    {
        $period->delete();
    }
}
