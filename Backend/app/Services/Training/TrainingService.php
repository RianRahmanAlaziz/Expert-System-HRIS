<?php

namespace App\Services\Training;

use App\Models\Training;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TrainingService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

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
                $training = Training::query()->create([
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

                $this->activityLogService->log(
                    action: 'create',
                    module: 'training',
                    target: $training,
                    newValues: [
                        'code' => $training->code,
                        'name' => $training->name,
                        'category' => $training->category,
                        'description' => $training->description,
                        'trainer' => $training->trainer,
                        'start_date' => $training->start_date?->toDateString(),
                        'end_date' => $training->end_date?->toDateString(),
                        'capacity' => $training->capacity,
                        'status' => $training->status,
                    ],
                );

                return $training;
            }

        );
    }

    public function update(
        Training $training,
        array $data,
    ): Training {
        $oldValues = [
            'code' => $training->code,
            'name' => $training->name,
            'category' => $training->category,
            'description' => $training->description,
            'trainer' => $training->trainer,
            'start_date' => $training->start_date?->toDateString(),
            'end_date' => $training->end_date?->toDateString(),
            'capacity' => $training->capacity,
            'status' => $training->status,
        ];

        DB::transaction(
            function () use ($training, $data): void {
                $training->update($data);
            }
        );

        $training->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'training',
            target: $training,
            oldValues: $oldValues,
            newValues: [
                'code' => $training->code,
                'name' => $training->name,
                'category' => $training->category,
                'description' => $training->description,
                'trainer' => $training->trainer,
                'start_date' => $training->start_date?->toDateString(),
                'end_date' => $training->end_date?->toDateString(),
                'capacity' => $training->capacity,
                'status' => $training->status,
            ],
        );

        return $training;
    }

    public function updateStatus(
        Training $training,
        string $status,
    ): Training {
        $oldStatus = $training->status;

        DB::transaction(
            function () use ($training, $status): void {
                $training->update([
                    'status' => $status,
                ]);
            }
        );

        $training->refresh();

        $this->activityLogService->log(
            action: 'update_status',
            module: 'training',
            target: $training,
            oldValues: [
                'status' => $oldStatus,
            ],
            newValues: [
                'status' => $training->status,
            ],
        );

        return $training;
    }

    public function delete(Training $training): void
    {
        $oldValues = [
            'code' => $training->code,
            'name' => $training->name,
            'category' => $training->category,
            'description' => $training->description,
            'trainer' => $training->trainer,
            'start_date' => $training->start_date?->toDateString(),
            'end_date' => $training->end_date?->toDateString(),
            'capacity' => $training->capacity,
            'status' => $training->status,
        ];

        DB::transaction(
            function () use ($training): void {
                $training->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'training',
            target: $training,
            oldValues: $oldValues,
        );
    }
}
