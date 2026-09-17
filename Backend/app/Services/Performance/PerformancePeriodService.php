<?php

namespace App\Services\Performance;

use App\Models\PerformancePeriod;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PerformancePeriodService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

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
        $period = PerformancePeriod::create($data);

        $this->activityLogService->log(
            action: 'create',
            module: 'performance_period',
            target: $period,
            newValues: [
                'name' => $period->name,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'status' => $period->status,
                'description' => $period->description,
            ],
        );

        return $period;
    }

    public function update(
        PerformancePeriod $period,
        array $data
    ): PerformancePeriod {
        $oldValues = [
            'name' => $period->name,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'status' => $period->status,
            'description' => $period->description,
        ];

        $period->update($data);
        $period->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'performance_period',
            target: $period,
            oldValues: $oldValues,
            newValues: [
                'name' => $period->name,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'status' => $period->status,
                'description' => $period->description,
            ],
        );

        return $period;
    }

    public function delete(PerformancePeriod $period): void
    {
        $this->activityLogService->log(
            action: 'delete',
            module: 'performance_period',
            target: $period,
            oldValues: [
                'name' => $period->name,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'status' => $period->status,
                'description' => $period->description,
            ],
        );

        $period->delete();
    }
}
