<?php

namespace App\Services\Department;

use App\Models\Department;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return Department::query()
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

    public function findById(int $id): Department
    {
        return Department::query()->findOrFail($id);
    }

    public function create(array $data): Department
    {
        return DB::transaction(
            function () use ($data): Department {
                $department = Department::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                    'is_active' => $data['is_active'] ?? true,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'department',
                    target: $department,
                    newValues: [
                        'code' => $department->code,
                        'name' => $department->name,
                        'description' => $department->description,
                        'status' => $department->status,
                        'is_active' => $department->is_active,
                    ],
                );

                return $department;
            }
        );
    }

    public function update(
        Department $department,
        array $data,
    ): Department {
        $oldValues = [
            'code' => $department->code,
            'name' => $department->name,
            'description' => $department->description,
            'status' => $department->status,
            'is_active' => $department->is_active,
        ];

        DB::transaction(
            function () use ($department, $data): void {
                $department->update($data);
            }
        );

        $department->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'department',
            target: $department,
            oldValues: $oldValues,
            newValues: [
                'code' => $department->code,
                'name' => $department->name,
                'description' => $department->description,
                'status' => $department->status,
                'is_active' => $department->is_active,
            ],
        );

        return $department;
    }

    public function delete(Department $department): void
    {
        $oldValues = [
            'code' => $department->code,
            'name' => $department->name,
            'description' => $department->description,
            'status' => $department->status,
            'is_active' => $department->is_active,
        ];

        DB::transaction(
            static function () use ($department): void {
                $department->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'department',
            target: $department,
            oldValues: $oldValues,
        );
    }
}
