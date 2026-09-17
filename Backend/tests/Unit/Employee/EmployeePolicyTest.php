<?php

namespace Tests\Unit\Employee;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Policies\EmployeePolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected EmployeePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->policy = app(EmployeePolicy::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createUser(
        ?string $role = null,
        array $permissions = [],
    ): User {
        $user = User::factory()->create();

        if ($role !== null) {
            $user->assignRole($role);
        }

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'HR-' . fake()->unique()->numerify('###'),
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => 'POS-' . fake()->unique()->numerify('###'),
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        ?User $user = null,
        ?int $managerId = null,
    ): Employee {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        return Employee::query()->create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $managerId,
            'employee_number' => 'EMP-' . fake()->unique()->numerify('#####'),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    private function removeRolePermissions(User $user): void
    {
        $role = $user->roles()->first();

        if ($role !== null) {
            $role->syncPermissions([]);
        }

        $user->syncPermissions([]);
    }

    /*
    |--------------------------------------------------------------------------
    | viewAny()
    |--------------------------------------------------------------------------
    */

    public function test_view_any_allows_user_with_employee_view_permission(): void
    {
        $user = $this->createUser(
            permissions: ['employee.view'],
        );

        $this->assertTrue(
            $this->policy->viewAny($user)
        );
    }

    public function test_view_any_denies_user_without_employee_view_permission(): void
    {
        $user = $this->createUser();

        $this->assertFalse(
            $this->policy->viewAny($user)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | view()
    |--------------------------------------------------------------------------
    */

    public function test_view_allows_super_admin(): void
    {
        $user = $this->createUser(
            role: 'super-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->view($user, $employee)
        );
    }

    public function test_view_allows_admin(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->view($user, $employee)
        );
    }

    public function test_view_allows_hr_admin(): void
    {
        $user = $this->createUser(
            role: 'hr-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->view($user, $employee)
        );
    }

    public function test_view_denies_privileged_user_without_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $this->removeRolePermissions($user);

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->view($user, $employee)
        );
    }

    public function test_view_allows_manager_to_view_direct_subordinate(): void
    {
        $managerUser = $this->createUser(
            role: 'manager',
        );

        $managerEmployee = $this->createEmployee(
            user: $managerUser,
        );

        $subordinate = $this->createEmployee(
            managerId: $managerEmployee->id,
        );

        $this->assertTrue(
            $this->policy->view($managerUser, $subordinate)
        );
    }

    public function test_view_denies_manager_to_view_non_subordinate(): void
    {
        $managerUser = $this->createUser(
            role: 'manager',
        );

        $this->createEmployee(
            user: $managerUser,
        );

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->view($managerUser, $employee)
        );
    }

    public function test_view_denies_manager_without_employee_record(): void
    {
        $managerUser = $this->createUser(
            role: 'manager',
        );

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->view($managerUser, $employee)
        );
    }

    public function test_view_allows_employee_to_view_themselves(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $employee = $this->createEmployee(
            user: $user,
        );

        $this->assertTrue(
            $this->policy->view($user, $employee)
        );
    }

    public function test_view_denies_employee_to_view_other_employee(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $this->createEmployee(
            user: $user,
        );

        $otherEmployee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->view($user, $otherEmployee)
        );
    }

    public function test_view_denies_employee_without_permission(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $this->removeRolePermissions($user);

        $employee = $this->createEmployee(
            user: $user,
        );

        $this->assertFalse(
            $this->policy->view($user, $employee)
        );
    }

    public function test_view_denies_user_without_supported_role(): void
    {
        $user = $this->createUser(
            permissions: ['employee.view'],
        );

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->view($user, $employee)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | create()
    |--------------------------------------------------------------------------
    */

    public function test_create_allows_super_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'super-admin',
        );

        $this->assertTrue(
            $this->policy->create($user)
        );
    }

    public function test_create_allows_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $this->assertTrue(
            $this->policy->create($user)
        );
    }

    public function test_create_allows_hr_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'hr-admin',
        );

        $this->assertTrue(
            $this->policy->create($user)
        );
    }

    public function test_create_denies_manager(): void
    {
        $user = $this->createUser(
            role: 'manager',
        );

        $this->assertFalse(
            $this->policy->create($user)
        );
    }

    public function test_create_denies_employee(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $this->assertFalse(
            $this->policy->create($user)
        );
    }

    public function test_create_denies_privileged_user_without_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $this->removeRolePermissions($user);

        $this->assertFalse(
            $this->policy->create($user)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | update()
    |--------------------------------------------------------------------------
    */

    public function test_update_allows_super_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'super-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->update($user, $employee)
        );
    }

    public function test_update_allows_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->update($user, $employee)
        );
    }

    public function test_update_allows_hr_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'hr-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->update($user, $employee)
        );
    }

    public function test_update_denies_manager(): void
    {
        $user = $this->createUser(
            role: 'manager',
        );

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->update($user, $employee)
        );
    }

    public function test_update_denies_employee(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->update($user, $employee)
        );
    }

    public function test_update_denies_privileged_user_without_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $this->removeRolePermissions($user);

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->update($user, $employee)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | delete()
    |--------------------------------------------------------------------------
    */

    public function test_delete_allows_super_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'super-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->delete($user, $employee)
        );
    }

    public function test_delete_allows_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->delete($user, $employee)
        );
    }

    public function test_delete_allows_hr_admin_with_permission(): void
    {
        $user = $this->createUser(
            role: 'hr-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->delete($user, $employee)
        );
    }

    public function test_delete_denies_manager(): void
    {
        $user = $this->createUser(
            role: 'manager',
        );

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->delete($user, $employee)
        );
    }

    public function test_delete_denies_employee(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->delete($user, $employee)
        );
    }

    public function test_delete_denies_privileged_user_without_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $this->removeRolePermissions($user);

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->delete($user, $employee)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | viewSensitive()
    |--------------------------------------------------------------------------
    */

    public function test_view_sensitive_allows_privileged_user(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->viewSensitive($user, $employee)
        );
    }

    public function test_view_sensitive_allows_employee_to_view_their_own_data(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $employee = $this->createEmployee(
            user: $user,
        );

        $this->assertTrue(
            $this->policy->viewSensitive($user, $employee)
        );
    }

    public function test_view_sensitive_denies_employee_to_view_other_employee(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $this->createEmployee(
            user: $user,
        );

        $otherEmployee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->viewSensitive($user, $otherEmployee)
        );
    }

    public function test_view_sensitive_denies_manager_even_for_direct_subordinate(): void
    {
        $managerUser = $this->createUser(
            role: 'manager',
        );

        $managerEmployee = $this->createEmployee(
            user: $managerUser,
        );

        $subordinate = $this->createEmployee(
            managerId: $managerEmployee->id,
        );

        $this->assertFalse(
            $this->policy->viewSensitive($managerUser, $subordinate)
        );
    }

    public function test_view_sensitive_denies_user_without_view_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $this->removeRolePermissions($user);

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->viewSensitive($user, $employee)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | viewAccount()
    |--------------------------------------------------------------------------
    */

    public function test_view_account_allows_privileged_user(): void
    {
        $user = $this->createUser(
            role: 'hr-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->viewAccount($user, $employee)
        );
    }

    public function test_view_account_allows_employee_to_view_their_own_account(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $employee = $this->createEmployee(
            user: $user,
        );

        $this->assertTrue(
            $this->policy->viewAccount($user, $employee)
        );
    }

    public function test_view_account_denies_employee_to_view_other_account(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $this->createEmployee(
            user: $user,
        );

        $otherEmployee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->viewAccount($user, $otherEmployee)
        );
    }

    public function test_view_account_denies_manager_for_subordinate(): void
    {
        $managerUser = $this->createUser(
            role: 'manager',
        );

        $managerEmployee = $this->createEmployee(
            user: $managerUser,
        );

        $subordinate = $this->createEmployee(
            managerId: $managerEmployee->id,
        );

        $this->assertFalse(
            $this->policy->viewAccount($managerUser, $subordinate)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | viewEmploymentHistory()
    |--------------------------------------------------------------------------
    */

    public function test_view_employment_history_allows_super_admin(): void
    {
        $user = $this->createUser(
            role: 'super-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->viewEmploymentHistory($user, $employee)
        );
    }

    public function test_view_employment_history_allows_admin(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->viewEmploymentHistory($user, $employee)
        );
    }

    public function test_view_employment_history_allows_hr_admin(): void
    {
        $user = $this->createUser(
            role: 'hr-admin',
        );

        $employee = $this->createEmployee();

        $this->assertTrue(
            $this->policy->viewEmploymentHistory($user, $employee)
        );
    }

    public function test_view_employment_history_denies_manager(): void
    {
        $managerUser = $this->createUser(
            role: 'manager',
        );

        $managerEmployee = $this->createEmployee(
            user: $managerUser,
        );

        $subordinate = $this->createEmployee(
            managerId: $managerEmployee->id,
        );

        $this->assertFalse(
            $this->policy->viewEmploymentHistory(
                $managerUser,
                $subordinate,
            )
        );
    }

    public function test_view_employment_history_denies_employee(): void
    {
        $user = $this->createUser(
            role: 'employee',
        );

        $employee = $this->createEmployee(
            user: $user,
        );

        $this->assertFalse(
            $this->policy->viewEmploymentHistory(
                $user,
                $employee,
            )
        );
    }

    public function test_view_employment_history_denies_user_without_view_permission(): void
    {
        $user = $this->createUser(
            role: 'admin',
        );

        $this->removeRolePermissions($user);

        $employee = $this->createEmployee();

        $this->assertFalse(
            $this->policy->viewEmploymentHistory(
                $user,
                $employee,
            )
        );
    }
}
