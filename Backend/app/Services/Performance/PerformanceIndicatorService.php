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
        $indicator = PerformanceIndicator::create($data);

        $this->activityLogService->log(
            action: 'create',
            module: 'performance_indicator',
            target: $indicator,
            newValues: [
                'name' => $indicator->name,
                'description' => $indicator->description,
                'category' => $indicator->category,
                'target' => $indicator->target,
                'weight' => $indicator->weight,
                'measurement_type' => $indicator->measurement_type,
                'is_active' => $indicator->is_active,
            ],
        );

        return $indicator;
    }

    public function update(
        PerformanceIndicator $indicator,
        array $data
    ): PerformanceIndicator {
        $oldValues = [
            'name' => $indicator->name,
            'description' => $indicator->description,
            'category' => $indicator->category,
            'target' => $indicator->target,
            'weight' => $indicator->weight,
            'measurement_type' => $indicator->measurement_type,
            'is_active' => $indicator->is_active,
        ];

        $indicator->update($data);

        $indicator->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'performance_indicator',
            target: $indicator,
            oldValues: $oldValues,
            newValues: [
                'name' => $indicator->name,
                'description' => $indicator->description,
                'category' => $indicator->category,
                'target' => $indicator->target,
                'weight' => $indicator->weight,
                'measurement_type' => $indicator->measurement_type,
                'is_active' => $indicator->is_active,
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
                'category' => $indicator->category,
                'target' => $indicator->target,
                'weight' => $indicator->weight,
                'measurement_type' => $indicator->measurement_type,
                'is_active' => $indicator->is_active,
            ],
        );

        $indicator->delete();
    }
}
