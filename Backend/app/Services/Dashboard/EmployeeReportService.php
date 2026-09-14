<?php

namespace App\Services\Dashboard;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeReportService
{
    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $departmentId = null,
        ?int $positionId = null,
        ?string $employmentType = null,
        ?string $employmentStatus = null,
    ): LengthAwarePaginator {
        return Employee::query()
            ->with([
                'department',
                'position',
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
                },
            )
            ->when(
                $departmentId !== null,
                function ($query) use ($departmentId): void {
                    $query->where('department_id', $departmentId);
                },
            )
            ->when(
                $positionId !== null,
                function ($query) use ($positionId): void {
                    $query->where('position_id', $positionId);
                },
            )
            ->when(
                filled($employmentType),
                function ($query) use ($employmentType): void {
                    $query->where('employment_type', $employmentType);
                },
            )
            ->when(
                filled($employmentStatus),
                function ($query) use ($employmentStatus): void {
                    $query->where('employment_status', $employmentStatus);
                },
            )
            ->latest()
            ->paginate($perPage);
    }
}
