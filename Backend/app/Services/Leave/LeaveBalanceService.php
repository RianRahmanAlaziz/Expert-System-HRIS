<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeaveBalanceService
{
    public function paginate(
        int $perPage = 15,
        ?int $employeeId = null,
        ?int $leaveTypeId = null,
        ?int $year = null,
    ): LengthAwarePaginator {
        return LeaveBalance::query()
            ->with([
                'employee',
                'leaveType',
            ])
            ->when(
                $employeeId !== null,
                function ($query) use ($employeeId): void {
                    $query->where('employee_id', $employeeId);
                }
            )
            ->when(
                $leaveTypeId !== null,
                function ($query) use ($leaveTypeId): void {
                    $query->where('leave_type_id', $leaveTypeId);
                }
            )
            ->when(
                $year !== null,
                function ($query) use ($year): void {
                    $query->where('year', $year);
                }
            )
            ->latest('year')
            ->latest('id')
            ->paginate($perPage);
    }

    public function getMyBalances(
        Employee $employee,
        ?int $year = null,
    ): array {
        return LeaveBalance::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->when(
                $year !== null,
                function ($query) use ($year): void {
                    $query->where('year', $year);
                }
            )
            ->orderBy('leave_type_id')
            ->get()
            ->all();
    }

    public function getByEmployee(
        Employee $employee,
        ?int $year = null,
    ): array {
        return LeaveBalance::query()
            ->with([
                'employee',
                'leaveType',
            ])
            ->where('employee_id', $employee->id)
            ->when(
                $year !== null,
                function ($query) use ($year): void {
                    $query->where('year', $year);
                }
            )
            ->orderBy('leave_type_id')
            ->get()
            ->all();
    }

    public function findById(int $id): LeaveBalance
    {
        return LeaveBalance::query()
            ->with([
                'employee',
                'leaveType',
            ])->findOrFail($id);
    }
}
