<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
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
                return Department::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                    'is_active' => $data['is_active'] ?? true,
                ]);
            }
        );
    }

    public function update(
        Department $department,
        array $data,
    ): Department {
        DB::transaction(
            function () use ($department, $data): void {
                $department->update($data);
            }
        );

        return $department->refresh();
    }

    public function delete(Department $department): void
    {
        DB::transaction(
            static function () use ($department): void {
                $department->delete();
            }
        );
    }
}
