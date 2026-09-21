<?php

namespace App\Services\Performance;

use App\Models\PerformanceIndicator;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PerformanceIndicatorService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        string $search = '',
        ?string $status = null,
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
                $status !== null,
                fn($query) => $query->where('status', $status),
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function getActive(): Collection
    {
        return PerformanceIndicator::query()->where('status', 'active')->latest()->get();
    }

    public function getById(int $id): PerformanceIndicator
    {
        return PerformanceIndicator::query()->withCount('reviewItems')->findOrFail($id);
    }

    public function create(array $data): PerformanceIndicator
    {
        $indicator = PerformanceIndicator::create($data);

        $this->activityLogService->log(
            action: 'create',
            module: 'performance_indicator',
            target: $indicator,
            newValues: [
                'code' => $indicator->code,
                'name' => $indicator->name,
                'description' => $indicator->description,
                'weight' => $indicator->weight,
                'target' => $indicator->target,
                'unit' => $indicator->unit,
                'status' => $indicator->status,
            ],
        );

        return $indicator;
    }

    public function update(
        PerformanceIndicator $indicator,
        array $data
    ): PerformanceIndicator {
        $oldValues = [
            'code' => $indicator->code,
            'name' => $indicator->name,
            'description' => $indicator->description,
            'weight' => $indicator->weight,
            'target' => $indicator->target,
            'unit' => $indicator->unit,
            'status' => $indicator->status,
        ];

        $indicator->update($data);

        $indicator->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'performance_indicator',
            target: $indicator,
            oldValues: $oldValues,
            newValues: [
                'code' => $indicator->code,
                'name' => $indicator->name,
                'description' => $indicator->description,
                'weight' => $indicator->weight,
                'target' => $indicator->target,
                'unit' => $indicator->unit,
                'status' => $indicator->status,
            ],
        );

        return $indicator;
    }

    public function delete(PerformanceIndicator $indicator): void
    {
        $this->activityLogService->log(
            action: 'delete',
            module: 'performance_indicator',
            target: $indicator,
            oldValues: [
                'name' => $indicator->name,
                'description' => $indicator->description,
                'code' => $indicator->code,
                'target' => $indicator->target,
                'weight' => $indicator->weight,
                'unit' => $indicator->unit,
                'status' => $indicator->status,
            ],
        );

        $indicator->delete();
    }
}
