<?php

namespace App\Authorization;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class EmployeeScope
{
    /**
     * Apply employee visibility scope for the authenticated user.
     *
     * @param Builder<Employee> $query
     * @return Builder<Employee>
     */
    public function apply(
        Builder $query,
        User $user,
    ): Builder {
        if ($this->isPrivileged($user)) {
            return $query;
        }

        if ($user->hasRole('manager')) {
            $employeeId = $user->employee?->id;

            if ($employeeId === null) {
                return $query->whereKey(-1);
            }

            return $query->where('manager_id', $employeeId);
        }

        if ($user->hasRole('employee')) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereKey(-1);
    }

    private function isPrivileged(User $user): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ]);
    }
}
