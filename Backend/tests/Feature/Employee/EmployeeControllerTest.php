<?php

namespace Tests\Feature\Employee;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithPermission(string $permission): User
    {
        $user = User::factory()->create();

        $permission = Permission::findOrCreate(
            $permission,
            'web',
        );

        $role = Role::create([
            'name' => 'test-role-' . uniqid(),
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        return $user;
    }

    private function createDepartment(array $overrides = []): Department
    {
        return Department::query()->create(
            array_merge([
                'code' => 'DEP-' . strtoupper(substr(uniqid(), -6)),
                'name' => 'Technology',
                'description' => 'Technology Department',
                'status' => 'active',
                'is_active' => true,
            ], $overrides),
        );
    }

    private function createPosition(array $overrides = []): Position
    {
        return Position::query()->create(
            array_merge([
                'code' => 'POS-' . strtoupper(substr(uniqid(), -6)),
                'name' => 'Software Engineer',
                'description' => 'Software Engineer Position',
                'level' => 5,
                'status' => 'active',
                'is_active' => true,
            ], $overrides),
        );
    }

    private function createEmployee(
        array $overrides = [],
        ?Department $department = null,
        ?Position $position = null,
    ): Employee {
        $department ??= $this->createDepartment();
        $position ??= $this->createPosition();

        return Employee::query()->create(
            array_merge([
                'user_id' => null,
                'department_id' => $department->id,
                'position_id' => $position->id,
                'manager_id' => null,
                'employee_number' => 'EMP-' . strtoupper(substr(uniqid(), -6)),
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'male',
                'birth_date' => '1995-01-15',
                'phone' => '08123456789',
                'address' => 'Jakarta',
                'join_date' => '2025-01-01',
                'employment_type' => 'full_time',
                'employment_status' => 'active',
            ], $overrides),
        );
    }

    private function employeePayload(
        Department $department,
        Position $position,
        array $overrides = [],
    ): array {
        return array_merge([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-' . strtoupper(substr(uniqid(), -6)),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '08123456789',
            'address' => 'Jakarta',
            'join_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ], $overrides);
    }

    public function test_guest_cannot_access_employees(): void
    {
        $response = $this->getJson('/api/v1/employees');

        $response->assertUnauthorized();
    }

    public function test_user_without_employee_view_permission_cannot_list_employees(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/employees');

        $response->assertForbidden();
    }

    public function test_user_with_employee_view_permission_can_list_employees(): void
    {
        $user = $this->createUserWithPermission('employee.view');

        $this->createEmployee();
        $this->createEmployee();
        $this->createEmployee();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/employees');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
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

        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_with_employee_view_permission_can_search_employees(): void
    {
        $user = $this->createUserWithPermission('employee.view');

        $this->createEmployee([
            'employee_number' => 'EMP-SEARCH',
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
        ]);

        $this->createEmployee([
            'employee_number' => 'EMP-OTHER',
            'first_name' => 'Bob',
            'last_name' => 'Smith',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/employees?search=Alice');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_employee_list_supports_pagination(): void
    {
        $user = $this->createUserWithPermission('employee.view');

        for ($index = 1; $index <= 5; $index++) {
            $this->createEmployee([
                'employee_number' => sprintf('EMP-%03d', $index),
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/employees?per_page=2');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                5,
            );

        $this->assertCount(2, $response->json('data'));
    }

    public function test_employee_list_can_filter_by_department(): void
    {
        $user = $this->createUserWithPermission('employee.view');

        $departmentA = $this->createDepartment([
            'code' => 'DEP-A',
        ]);

        $departmentB = $this->createDepartment([
            'code' => 'DEP-B',
        ]);

        $this->createEmployee(
            department: $departmentA,
        );

        $this->createEmployee(
            department: $departmentB,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/employees?department_id={$departmentA->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            );
    }

    public function test_employee_list_can_filter_by_position(): void
    {
        $user = $this->createUserWithPermission('employee.view');

        $positionA = $this->createPosition([
            'code' => 'POS-A',
        ]);

        $positionB = $this->createPosition([
            'code' => 'POS-B',
        ]);

        $this->createEmployee(
            position: $positionA,
        );

        $this->createEmployee(
            position: $positionB,
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/employees?position_id={$positionA->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            );
    }

    public function test_employee_list_can_filter_by_employment_status(): void
    {
        $user = $this->createUserWithPermission('employee.view');

        $this->createEmployee([
            'employment_status' => 'active',
        ]);

        $this->createEmployee([
            'employment_status' => 'inactive',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/employees?employment_status=active',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            );
    }

    public function test_user_with_employee_view_permission_can_show_employee(): void
    {
        $user = $this->createUserWithPermission('employee.view');

        $employee = $this->createEmployee([
            'employee_number' => 'EMP-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJsonPath(
                'data.id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.employee_number',
                'EMP-001',
            )
            ->assertJsonPath(
                'data.first_name',
                'John',
            );
    }

    public function test_user_with_employee_view_permission_gets_404_for_unknown_employee(): void
    {
        $user = $this->createUserWithPermission('employee.view');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/employees/999999');

        $response->assertNotFound();
    }

    public function test_user_with_employee_create_permission_can_create_employee(): void
    {
        $user = $this->createUserWithPermission('employee.create');

        $department = $this->createDepartment();
        $position = $this->createPosition();

        $payload = $this->employeePayload(
            $department,
            $position,
            [
                'employee_number' => 'EMP-001',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/employees', $payload);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJsonPath(
                'data.employee_number',
                'EMP-001',
            )
            ->assertJsonPath(
                'data.first_name',
                'John',
            );

        $this->assertDatabaseHas('employees', [
            'employee_number' => 'EMP-001',
            'first_name' => 'John',
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);

        $this->assertDatabaseHas('employment_histories', [
            'employee_id' => $response->json('data.id'),
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);
    }

    public function test_user_without_employee_create_permission_cannot_create_employee(): void
    {
        $user = User::factory()->create();

        $department = $this->createDepartment();
        $position = $this->createPosition();

        $payload = $this->employeePayload(
            $department,
            $position,
            [
                'employee_number' => 'EMP-NO-PERMISSION',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/employees', $payload);

        $response->assertForbidden();

        $this->assertDatabaseMissing('employees', [
            'employee_number' => 'EMP-NO-PERMISSION',
        ]);
    }

    public function test_create_employee_requires_department_position_employee_number_first_name_gender_join_date_employment_type_and_employment_status(): void
    {
        $user = $this->createUserWithPermission('employee.create');

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/employees', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'department_id',
                'position_id',
                'employee_number',
                'first_name',
                'gender',
                'join_date',
                'employment_type',
                'employment_status',
            ]);
    }

    public function test_create_employee_rejects_duplicate_employee_number(): void
    {
        $user = $this->createUserWithPermission('employee.create');

        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->createEmployee(
            department: $department,
            position: $position,
            overrides: [
                'employee_number' => 'EMP-DUPLICATE',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/employees',
                $this->employeePayload(
                    $department,
                    $position,
                    [
                        'employee_number' => 'EMP-DUPLICATE',
                    ],
                ),
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_number',
            ]);
    }

    public function test_create_employee_rejects_invalid_department(): void
    {
        $user = $this->createUserWithPermission('employee.create');

        $position = $this->createPosition();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/employees',
                $this->employeePayload(
                    $this->createDepartment(),
                    $position,
                    [
                        'department_id' => 999999,
                    ],
                ),
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'department_id',
            ]);
    }

    public function test_user_with_employee_update_permission_can_update_employee(): void
    {
        $user = $this->createUserWithPermission('employee.update');

        $employee = $this->createEmployee([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'first_name' => 'Jonathan',
                    'last_name' => 'Smith',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.first_name',
                'Jonathan',
            )
            ->assertJsonPath(
                'data.last_name',
                'Smith',
            );

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Jonathan',
            'last_name' => 'Smith',
        ]);
    }

    public function test_user_without_employee_update_permission_cannot_update_employee(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee([
            'first_name' => 'John',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'first_name' => 'Jonathan',
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'John',
        ]);
    }

    public function test_update_employee_rejects_duplicate_employee_number(): void
    {
        $user = $this->createUserWithPermission('employee.update');

        $firstEmployee = $this->createEmployee([
            'employee_number' => 'EMP-001',
        ]);

        $secondEmployee = $this->createEmployee([
            'employee_number' => 'EMP-002',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$secondEmployee->id}",
                [
                    'employee_number' => $firstEmployee->employee_number,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_number',
            ]);
    }

    public function test_update_employee_cannot_assign_itself_as_manager(): void
    {
        $user = $this->createUserWithPermission('employee.update');

        $employee = $this->createEmployee();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'manager_id' => $employee->id,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'manager_id',
            ]);
    }

    public function test_user_with_employee_delete_permission_can_delete_employee(): void
    {
        $user = $this->createUserWithPermission('employee.delete');

        $employee = $this->createEmployee();

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data',
                null,
            );

        $this->assertSoftDeleted('employees', [
            'id' => $employee->id,
        ]);
    }

    public function test_user_without_employee_delete_permission_cannot_delete_employee(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/employees/{$employee->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'deleted_at' => null,
        ]);
    }

    public function test_guest_cannot_create_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $response = $this->postJson(
            '/api/v1/employees',
            $this->employeePayload(
                $department,
                $position,
            ),
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_update_employee(): void
    {
        $employee = $this->createEmployee();

        $response = $this->putJson(
            "/api/v1/employees/{$employee->id}",
            [
                'first_name' => 'Updated',
            ],
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_delete_employee(): void
    {
        $employee = $this->createEmployee();

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}");

        $response->assertUnauthorized();
    }
}
