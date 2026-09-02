<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // User
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
            // Role
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
            // Permission
            'permission.view',
            // Department
            'department.view',
            'department.create',
            'department.update',
            'department.delete',

            // Position
            'position.view',
            'position.create',
            'position.update',
            'position.delete',

            // Employee
            'employee.view',
            'employee.create',
            'employee.update',
            'employee.delete',

            //Attendance
            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',
            'attendance.view_all',
            'attendance.report',

            //Leave
            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_type.delete',

            'leave_balance.view',
            'leave_balance.view_all',

            'leave_request.view',
            'leave_request.create',
            'leave_request.approve',
            'leave_request.reject',
            'leave_request.cancel',

            'leave_report.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $hrAdmin = Role::firstOrCreate([
            'name' => 'hr-admin',
            'guard_name' => 'web',
        ]);

        $manager = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ]);

        $employee = Role::firstOrCreate([
            'name' => 'employee',
            'guard_name' => 'web',
        ]);

        $superAdmin->syncPermissions($permissions);
        $admin->syncPermissions($permissions);

        $hrAdmin->syncPermissions([
            'user.view',
            'user.create',
            'user.update',
            'role.view',
            'permission.view',
            'department.view',
            'department.create',
            'department.update',
            'department.delete',

            'position.view',
            'position.create',
            'position.update',
            'position.delete',

            'employee.view',
            'employee.create',
            'employee.update',
            'employee.delete',

            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',
            'attendance.view_all',
            'attendance.report',

            'leave_type.view',
            'leave_type.create',
            'leave_type.update',
            'leave_type.delete',

            'leave_balance.view',
            'leave_balance.view_all',

            'leave_request.view',
            'leave_request.approve',
            'leave_request.reject',
            'leave_request.cancel',

            'leave_report.view',
        ]);

        $manager->syncPermissions([
            'user.view',
            'employee.view',
        ]);

        $employee->syncPermissions([
            'user.view',
            'employee.view',
            'attendance.view',
            'attendance.clock_in',
            'attendance.clock_out',
            'leave_balance.view',
            'leave_request.view',
            'leave_request.create',
            'leave_request.cancel',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
