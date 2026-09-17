<?php

namespace App\Services\Position;

use App\Models\Position;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PositionService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

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
                $position = Position::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'level' => $data['level'],
                    'status' => $data['status'],
                    'is_active' => $data['is_active'] ?? true,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'position',
                    target: $position,
                    newValues: [
                        'code' => $position->code,
                        'name' => $position->name,
                        'description' => $position->description,
                        'level' => $position->level,
                        'status' => $position->status,
                        'is_active' => $position->is_active,
                    ],
                );

                return $position;
            }
        );
    }


    public function update(
        Position $position,
        array $data,
    ): Position {
        $oldValues = [
            'code' => $position->code,
            'name' => $position->name,
            'description' => $position->description,
            'level' => $position->level,
            'status' => $position->status,
            'is_active' => $position->is_active,
        ];

        DB::transaction(
            function () use ($position, $data): void {
                $position->update($data);
            }
        );

        $position->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'position',
            target: $position,
            oldValues: $oldValues,
            newValues: [
                'code' => $position->code,
                'name' => $position->name,
                'description' => $position->description,
                'level' => $position->level,
                'status' => $position->status,
                'is_active' => $position->is_active,
            ],
        );

        return $position;
    }

    public function delete(Position $position): void
    {
        $oldValues = [
            'code' => $position->code,
            'name' => $position->name,
            'description' => $position->description,
            'level' => $position->level,
            'status' => $position->status,
            'is_active' => $position->is_active,
        ];

        DB::transaction(
            static function () use ($position): void {
                $position->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'position',
            target: $position,
            oldValues: $oldValues,
        );
    }
}
