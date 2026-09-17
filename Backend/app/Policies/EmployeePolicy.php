<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /**
     * Determine whether the user can view any employees.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('employee.view');
    }

    /**
     * Determine whether the user can view the employee.
     */
    public function view(User $user, Employee $employee): bool
    {
        if (! $user->can('employee.view')) {
            return false;
        }

        if ($this->isPrivileged($user)) {
            return true;
        }

        if ($user->hasRole('manager')) {
            $managerEmployeeId = $user->employee?->id;

            if ($managerEmployeeId === null) {
                return false;
            }

            return $employee->manager_id === $managerEmployeeId;
        }

        if ($user->hasRole('employee')) {
            return $employee->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create an employee.
     */
    public function create(User $user): bool
    {
        return $user->can('employee.create')
            && $this->isPrivileged($user);
    }

    /**
     * Determine whether the user can update the employee.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employee.update')
            && $this->isPrivileged($user);
    }

    /**
     * Determine whether the user can delete the employee.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employee.delete')
            && $this->isPrivileged($user);
    }

    /**
     * Determine whether the user has privileged employee access.
     */
    private function isPrivileged(User $user): bool
    {
        return $user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ]);
    }

    /**
     * Determine whether the user can view sensitive employee data.
     */
    public function viewSensitive(
        User $user,
        Employee $employee,
    ): bool {
        if (! $this->view($user, $employee)) {
            return false;
        }

        return $this->isPrivileged($user)
            || (
                $user->hasRole('employee')
                && $employee->user_id === $user->id
            );
    }

    /**
     * Determine whether the user can view linked user account data.
     */
    public function viewAccount(
        User $user,
        Employee $employee,
    ): bool {
        if (! $this->view($user, $employee)) {
            return false;
        }

        return $this->isPrivileged($user)
            || (
                $user->hasRole('employee')
                && $employee->user_id === $user->id
            );
    }

    /**
     * Determine whether the user can view employment history.
     */
    public function viewEmploymentHistory(
        User $user,
        Employee $employee,
    ): bool {
        if (! $this->view($user, $employee)) {
            return false;
        }

        return $this->isPrivileged($user);
    }
}
