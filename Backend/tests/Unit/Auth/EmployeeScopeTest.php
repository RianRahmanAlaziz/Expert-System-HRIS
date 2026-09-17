<?php

namespace Tests\Unit\Auth;

use App\Authorization\EmployeeScope;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeScopeTest extends TestCase
{
    use RefreshDatabase;

    protected EmployeeScope $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->scope = new EmployeeScope();
    }

    public function test_privileged_user_can_see_all_employees(): void
    {
        $user = $this->createUser(role: 'admin');

        $employeeOne = $this->createEmployee();
        $employeeTwo = $this->createEmployee();

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $user,
        );

        $this->assertCount(
            2,
            $query->get(),
        );
    }

    public function test_super_admin_can_see_all_employees(): void
    {
        $user = $this->createUser(role: 'super-admin');

        $this->createEmployee();
        $this->createEmployee();
        $this->createEmployee();

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $user,
        );

        $this->assertCount(
            3,
            $query->get(),
        );
    }

    public function test_hr_admin_can_see_all_employees(): void
    {
        $user = $this->createUser(role: 'hr-admin');

        $this->createEmployee();
        $this->createEmployee();

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $user,
        );

        $this->assertCount(
            2,
            $query->get(),
        );
    }

    public function test_manager_can_only_see_direct_subordinates(): void
    {
        $managerUser = $this->createUser(role: 'manager');

        $manager = $this->createEmployee(
            user: $managerUser,
        );

        $subordinateOne = $this->createEmployee(
            managerId: $manager->id,
        );

        $subordinateTwo = $this->createEmployee(
            managerId: $manager->id,
        );

        $otherEmployee = $this->createEmployee();

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $managerUser,
        );

        $employees = $query->get();

        $this->assertCount(2, $employees);

        $this->assertTrue(
            $employees->contains('id', $subordinateOne->id)
        );

        $this->assertTrue(
            $employees->contains('id', $subordinateTwo->id)
        );

        $this->assertFalse(
            $employees->contains('id', $otherEmployee->id)
        );
    }

    public function test_manager_cannot_see_themselves(): void
    {
        $managerUser = $this->createUser(role: 'manager');

        $manager = $this->createEmployee(
            user: $managerUser,
        );

        $this->createEmployee(
            managerId: $manager->id,
        );

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $managerUser,
        );

        $employees = $query->get();

        $this->assertFalse(
            $employees->contains('id', $manager->id)
        );
    }

    public function test_manager_without_employee_record_sees_no_employees(): void
    {
        $managerUser = $this->createUser(role: 'manager');

        $this->createEmployee();
        $this->createEmployee();

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $managerUser,
        );

        $this->assertCount(
            0,
            $query->get(),
        );
    }

    public function test_employee_can_only_see_themselves(): void
    {
        $employeeUser = $this->createUser(role: 'employee');

        $employee = $this->createEmployee(
            user: $employeeUser,
        );

        $otherEmployee = $this->createEmployee();

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $employeeUser,
        );

        $employees = $query->get();

        $this->assertCount(1, $employees);

        $this->assertTrue(
            $employees->contains('id', $employee->id)
        );

        $this->assertFalse(
            $employees->contains('id', $otherEmployee->id)
        );
    }

    public function test_employee_without_employee_record_sees_no_employees(): void
    {
        $employeeUser = $this->createUser(role: 'employee');

        $this->createEmployee();
        $this->createEmployee();

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $employeeUser,
        );

        $this->assertCount(
            0,
            $query->get(),
        );
    }

    public function test_user_without_supported_role_sees_no_employees(): void
    {
        $user = $this->createUser();

        $this->createEmployee();
        $this->createEmployee();

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $user,
        );

        $this->assertCount(
            0,
            $query->get(),
        );
    }

    public function test_manager_cannot_see_indirect_subordinates(): void
    {
        $managerUser = $this->createUser(role: 'manager');

        $manager = $this->createEmployee(
            user: $managerUser,
        );

        $directSubordinate = $this->createEmployee(
            managerId: $manager->id,
        );

        $indirectSubordinate = $this->createEmployee(
            managerId: $directSubordinate->id,
        );

        $query = Employee::query();

        $this->scope->apply(
            query: $query,
            user: $managerUser,
        );

        $employees = $query->get();

        $this->assertTrue(
            $employees->contains('id', $directSubordinate->id)
        );

        $this->assertFalse(
            $employees->contains('id', $indirectSubordinate->id)
        );
    }

    private function createUser(?string $role = null): User
    {
        $user = User::factory()->create();

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'DEP-' . fake()->unique()->numerify('####'),
            'name' => 'Department ' . fake()->unique()->numerify('####'),
            'description' => 'Test department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => 'POS-' . fake()->unique()->numerify('####'),
            'name' => 'Position ' . fake()->unique()->numerify('####'),
            'description' => 'Test position',
            'level' => 1,
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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => 'male',
            'birth_date' => '1995-01-01',
            'phone' => '08123456789',
            'address' => 'Test Address',
            'join_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }
}
