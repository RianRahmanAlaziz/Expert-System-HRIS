<?php

namespace App\Services;

use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

class RoleService
{
    /**
     * System roles that cannot be renamed or deleted.
     */
    private const SYSTEM_ROLES = [
        'super-admin',
        'admin',
        'hr-admin',
        'manager',
        'employee',
    ];

    /**
     * Get all roles with their permissions.
     */
    public function getAll(): LengthAwarePaginator
    {
        return Role::query()->with('permissions')->orderBy('name')->paginate(15);
    }

    /**
     * Create a new role.
     */
    public function create(
        string $name,
        array $permissions = []
    ): Role {
        $role = Role::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);
        return $role->load('permissions');
    }

    /**
     * Update an existing role.
     */
    public function update(
        Role $role,
        string $name,
        array $permissions = []
    ): Role {
        /*
         * Super admin is fully protected.
         */
        if ($role->name === 'super-admin') {
            throw new DomainException(
                'Role super-admin tidak dapat diubah.'
            );
        }

        /*
         * Other system roles cannot be renamed.
         */
        if (
            $this->isSystemRole($role)
            && $role->name !== $name
        ) {
            throw new DomainException(
                'System role tidak dapat diubah namanya.'
            );
        }

        $role->update([
            'name' => $name,
        ]);

        $role->syncPermissions($permissions);
        return $role->load('permissions');
    }


    /**
     * Delete a role.
     */
    public function delete(Role $role): void
    {
        if ($this->isSystemRole($role)) {
            throw new DomainException('System role tidak dapat dihapus.');
        }
        $role->delete();
    }

    /**
     * Determine whether the role is a system role.
     */
    private function isSystemRole(Role $role): bool
    {
        return in_array(
            $role->name,
            self::SYSTEM_ROLES,
            true
        );
    }
}
