<?php

namespace App\Services\Performance;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PerformanceHistoryService
{
    public function paginateHistory(
        User $user,
        int $perPage = 15,
        ?Employee $employee = null,
    ): LengthAwarePaginator {
        $query = PerformanceReview::query()
            ->with([
                'employee',
                'period',
                'reviewer',
            ])
            ->where('status', 'approved')
            ->latest('review_date')
            ->latest('id');

        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            if ($employee) {
                $query->where(
                    'employee_id',
                    $employee->id,
                );
            }

            return $query->paginate($perPage);
        }

        if ($user->hasRole('manager')) {
            $query->whereHas(
                'employee',
                function ($employeeQuery) use ($user): void {
                    $employeeQuery->whereHas(
                        'manager',
                        function ($managerQuery) use ($user): void {
                            $managerQuery->where(
                                'user_id',
                                $user->id,
                            );
                        },
                    );
                },
            );

            if ($employee) {
                $query->where(
                    'employee_id',
                    $employee->id,
                );
            }

            return $query->paginate($perPage);
        }

        if ($user->hasRole('employee')) {
            if (
                $employee &&
                $employee->user_id !== $user->id
            ) {
                abort(
                    403,
                    'Anda tidak memiliki akses ke performance history employee ini.',
                );
            }

            $query->whereHas(
                'employee',
                function ($employeeQuery) use ($user): void {
                    $employeeQuery->where(
                        'user_id',
                        $user->id,
                    );
                },
            );

            return $query->paginate($perPage);
        }

        return $query
            ->whereRaw('1 = 0')
            ->paginate($perPage);
    }
}
