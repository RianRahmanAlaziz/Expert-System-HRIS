<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $departmentId = null,
        ?int $positionId = null,
        ?int $managerId = null,
        ?string $employmentType = null,
        ?string $employmentStatus = null,
    ): LengthAwarePaginator {
        return Employee::query()
            ->with([
                'user',
                'department',
                'position',
                'manager',
            ])
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $departmentId !== null,
                function ($query) use ($departmentId): void {
                    $query->where('department_id', $departmentId);
                }
            )
            ->when(
                $positionId !== null,
                function ($query) use ($positionId): void {
                    $query->where('position_id', $positionId);
                }
            )
            ->when(
                $managerId !== null,
                function ($query) use ($managerId): void {
                    $query->where('manager_id', $managerId);
                }
            )
            ->when(
                filled($employmentType),
                function ($query) use ($employmentType): void {
                    $query->where('employment_type', $employmentType);
                }
            )
            ->when(
                filled($employmentStatus),
                function ($query) use ($employmentStatus): void {
                    $query->where('employment_status', $employmentStatus);
                }
            )
            ->latest()
            ->paginate($perPage);
    }


    public function findById(int $id): Employee
    {
        return Employee::query()
            ->with([
                'user',
                'department',
                'position',
                'manager',
                'subordinates',
                'employmentHistories.department',
                'employmentHistories.position',
                'employmentHistories.manager',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Employee
    {
        return DB::transaction(
            function () use ($data): Employee {
                $employee = Employee::query()->create([
                    'user_id' => $data['user_id'] ?? null,
                    'department_id' => $data['department_id'],
                    'position_id' => $data['position_id'],
                    'manager_id' => $data['manager_id'] ?? null,
                    'employee_number' => $data['employee_number'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? null,
                    'gender' => $data['gender'],
                    'birth_date' => $data['birth_date'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'join_date' => $data['join_date'],
                    'employment_type' => $data['employment_type'],
                    'employment_status' => $data['employment_status'],
                ]);

                $employee->employmentHistories()->create([
                    'department_id' => $employee->department_id,
                    'position_id' => $employee->position_id,
                    'manager_id' => $employee->manager_id,
                    'employment_type' => $employee->employment_type,
                    'start_date' => $employee->join_date,
                    'end_date' => null,
                    'reason' => $data['history_reason'] ?? 'Initial employment',
                    'notes' => $data['history_notes'] ?? null,
                ]);

                return $employee->load([
                    'user',
                    'department',
                    'position',
                    'manager',
                    'employmentHistories',
                ]);
            }
        );
    }

    public function update(Employee $employee,  array $data): Employee
    {
        DB::transaction(
            function () use ($employee, $data): void {
                $employee->update($data);
            }
        );
        return $employee->refresh();
    }

    public function delete(Employee $employee): void
    {
        DB::transaction(
            static function () use ($employee): void {
                $employee->delete();
            }
        );
    }
}
