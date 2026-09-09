<?php

namespace App\Services\Performance;

use App\Models\PerformancePeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PerformancePeriodService
{
    public function paginate(
        int $perPage = 15,
        string $search = '',
        ?string $status = null,
    ): LengthAwarePaginator {
        return PerformancePeriod::query()
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
                $status !== null,
                fn($query) => $query->where('status', $status),
            )
            ->latest('start_date')
            ->latest('id')
            ->paginate($perPage);
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
