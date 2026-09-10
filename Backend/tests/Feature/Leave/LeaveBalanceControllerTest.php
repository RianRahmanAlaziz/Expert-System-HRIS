<?php

namespace Tests\Feature\Leave;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveBalanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_guest_cannot_list_leave_balances(): void
    {
        $response = $this->getJson('/api/v1/leave-balances');

        $response->assertUnauthorized();
    }

    public function test_user_without_view_all_permission_cannot_list_leave_balances(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances');

        $response->assertForbidden();
    }

    public function test_user_with_view_all_permission_can_list_leave_balances(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Daftar Leave Balance berhasil diambil.',
            )
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                        'from',
                        'to',
                    ],
                ],
            ]);
    }

    public function test_leave_balance_list_can_filter_by_employee(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employeeOne = $this->createEmployee([
            'employee_number' => 'EMP-001',
        ]);

        $employeeTwo = $this->createEmployee([
            'employee_number' => 'EMP-002',
        ]);

        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employeeOne,
            leaveType: $leaveType,
        );

        $this->createLeaveBalance(
            employee: $employeeTwo,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-balances?employee_id={$employeeOne->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employeeOne->id,
            );
    }

    public function test_leave_balance_list_can_filter_by_leave_type(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employee = $this->createEmployee();

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $annual,
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $sick,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-balances?leave_type_id={$annual->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.leave_type_id',
                $annual->id,
            );
    }

    public function test_leave_balance_list_can_filter_by_year(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'year' => 2025,
            ],
        );

        $leaveTypeTwo = $this->createLeaveType([
            'code' => 'ANNUAL-2026',
        ]);

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveTypeTwo,
            attributes: [
                'year' => 2026,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances?year=2026');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.year', 2026);
    }

    public function test_leave_balance_list_can_filter_by_multiple_filters(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employeeOne = $this->createEmployee();
        $employeeTwo = $this->createEmployee();

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveBalance(
            employee: $employeeOne,
            leaveType: $annual,
            attributes: [
                'year' => 2026,
            ],
        );

        $this->createLeaveBalance(
            employee: $employeeOne,
            leaveType: $sick,
            attributes: [
                'year' => 2025,
            ],
        );

        $this->createLeaveBalance(
            employee: $employeeTwo,
            leaveType: $annual,
            attributes: [
                'year' => 2026,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-balances"
                    . "?employee_id={$employeeOne->id}"
                    . "&leave_type_id={$annual->id}"
                    . "&year=2026",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employeeOne->id,
            )
            ->assertJsonPath(
                'data.0.leave_type_id',
                $annual->id,
            )
            ->assertJsonPath(
                'data.0.year',
                2026,
            );
    }

    public function test_leave_balance_list_can_paginate(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employee = $this->createEmployee();

        foreach (range(1, 3) as $index) {
            $leaveType = $this->createLeaveType([
                'code' => "TYPE-{$index}",
            ]);

            $this->createLeaveBalance(
                employee: $employee,
                leaveType: $leaveType,
            );
        }

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_leave_balance_list_rejects_unknown_employee(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances?employee_id=999999');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'employee_id',
        ]);
    }

    public function test_leave_balance_list_rejects_unknown_leave_type(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances?leave_type_id=999999');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'leave_type_id',
        ]);
    }

    public function test_leave_balance_list_rejects_invalid_year(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances?year=1999');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'year',
        ]);
    }

    public function test_leave_balance_list_rejects_year_above_2100(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances?year=2101');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'year',
        ]);
    }

    public function test_leave_balance_list_rejects_invalid_per_page(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances?per_page=101');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'per_page',
        ]);
    }

    public function test_guest_cannot_access_my_leave_balances(): void
    {
        $response = $this->getJson('/api/v1/leave-balances/me');

        $response->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_access_my_leave_balances(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances/me');

        $response->assertForbidden();
    }

    public function test_employee_can_access_my_leave_balances(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances/me');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Balance berhasil diambil.',
            )
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.0.leave_type_id',
                $leaveType->id,
            );
    }

    public function test_my_leave_balances_only_return_authenticated_employee_data(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $otherEmployee = $this->createEmployee();

        $leaveType = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $otherLeaveType = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $this->createLeaveBalance(
            employee: $otherEmployee,
            leaveType: $otherLeaveType,
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances/me');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employee->id,
            );
    }

    public function test_my_leave_balances_can_filter_by_year(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $leaveTypeTwo = $this->createLeaveType([
            'code' => 'ANNUAL-2026',
        ]);

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'year' => 2025,
            ],
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveTypeTwo,
            attributes: [
                'year' => 2026,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances/me?year=2026');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.year', 2026);
    }

    public function test_my_leave_balances_returns_422_when_user_has_no_employee(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances/me');

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'User tidak memiliki data employee.',
            );
    }

    public function test_guest_cannot_access_employee_leave_balances(): void
    {
        $employee = $this->createEmployee();

        $response = $this->getJson(
            "/api/v1/leave-balances/{$employee->id}",
        );

        $response->assertUnauthorized();
    }

    public function test_user_without_view_all_permission_cannot_access_employee_leave_balances(): void
    {
        $user = $this->createUser();

        $employee = $this->createEmployee();

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-balances/{$employee->id}",
            );

        $response->assertForbidden();
    }

    public function test_user_with_view_all_permission_can_access_employee_leave_balances(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employee = $this->createEmployee();

        $leaveType = $this->createLeaveType();

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-balances/{$employee->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Balance employee berhasil diambil.',
            )
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employee->id,
            );
    }

    public function test_employee_leave_balances_can_filter_by_year(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employee = $this->createEmployee();

        $leaveType = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $leaveTypeTwo = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'year' => 2025,
            ],
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveTypeTwo,
            attributes: [
                'year' => 2026,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-balances/{$employee->id}?year=2026",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.year', 2026);
    }

    public function test_employee_leave_balances_return_empty_data_when_employee_has_no_balance(): void
    {
        $user = $this->createUserWithPermission(
            'leave_balance.view_all',
        );

        $employee = $this->createEmployee();

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-balances/{$employee->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Balance employee berhasil diambil.',
            )
            ->assertJsonCount(0, 'data');
    }

    public function test_employee_leave_balances_return_404_for_unknown_employee(): void
    {
        $user = $this->createUserWithPermission('leave_balance.view_all');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances/999999');

        $response->assertNotFound();
    }

    public function test_my_leave_balances_reject_invalid_year(): void
    {
        $user = $this->createUserWithPermission('leave_balance.view');

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-balances/me?year=1999');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'year',
        ]);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createUserWithPermission(string $permission): User
    {
        $user = $this->createUser();

        $permissionModel = Permission::findOrCreate(
            $permission,
            'web',
        );

        $role = Role::findOrCreate(
            'test-role-' . uniqid(),
            'web',
        );

        $role->givePermissionTo($permissionModel);

        $user->assignRole($role);

        return $user;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEmployee(array $attributes = []): Employee
    {
        $department = Department::query()->create([
            'code' => 'DEPT-' . uniqid(),
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
        ]);

        $position = Position::query()->create([
            'code' => 'POS-' . uniqid(),
            'name' => 'Staff',
            'description' => null,
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);

        return Employee::query()->create(array_merge([
            'user_id' => null,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => null,
            'employee_number' => 'EMP-' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'gender' => 'male',
            'birth_date' => null,
            'phone' => null,
            'address' => null,
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createLeaveType(array $attributes = []): LeaveType
    {
        return LeaveType::query()->create(array_merge([
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL-' . uniqid(),
            'default_days' => 12,
            'description' => 'Cuti tahunan karyawan.',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createLeaveBalance(
        Employee $employee,
        LeaveType $leaveType,
        array $attributes = [],
    ): LeaveBalance {
        return LeaveBalance::query()->create(array_merge([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated_days' => 12.00,
            'used_days' => 2.00,
            'remaining_days' => 10.00,
        ], $attributes));
    }
}
