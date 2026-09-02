<?php

namespace App\Services\Performance;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PerformanceHistoryService
{
    public function getHistory(
        User $user,
        ?Employee $employee = null
    ): Collection {
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
                    $employee->id
                );
            }

            return $query->get();
        }

        if ($user->hasRole('manager')) {
            $query->whereHas('employee', function ($employeeQuery) use ($user) {
                $employeeQuery->whereHas('manager', function ($managerQuery) use ($user) {
                    $managerQuery->where('user_id', $user->id);
                });
            });

            if ($employee) {
                $query->where(
                    'employee_id',
                    $employee->id
                );
            }

            return $query->get();
        }

        if ($user->hasRole('employee')) {
            if (
                $employee &&
                $employee->user_id !== $user->id
            ) {
                abort(403, 'Anda tidak memiliki akses ke performance history employee ini.');
            }

            $query->whereHas('employee', function ($employeeQuery) use ($user) {
                $employeeQuery->where(
                    'user_id',
                    $user->id
                );
            });

            return $query->get();
        }

        return new Collection();
    }
}
