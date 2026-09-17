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

    private function createUserWithRole(
        string $roleName,
        string $permission,
    ): User {
        $user = User::factory()->create();

        $permission = Permission::findOrCreate(
            $permission,
            'web',
        );

        $role = Role::findOrCreate(
            $roleName,
            'web',
        );

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

    public function test_hr_admin_can_list_all_employees(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

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

    public function test_hr_admin_can_search_employees(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

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

    public function test_hr_admin_can_show_employee(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/employees/999999');

        $response->assertNotFound();
    }

    public function test_user_with_employee_create_permission_can_create_employee(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

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
            ->assertCreated()
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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

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
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.delete',
        );

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

    public function test_hr_admin_can_delete_employee(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.delete',
        );

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

    public function test_employee_can_only_list_self(): void
    {
        $user = User::factory()->create();

        $role = Role::findOrCreate(
            'employee',
            'web',
        );

        $permission = Permission::findOrCreate(
            'employee.view',
            'web',
        );

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $ownEmployee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $otherUser = User::factory()->create();

        $this->createEmployee([
            'user_id' => $otherUser->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/employees');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownEmployee->id,
            );
    }

    public function test_employee_cannot_show_other_employee(): void
    {
        $user = User::factory()->create();

        $role = Role::findOrCreate(
            'employee',
            'web',
        );

        $permission = Permission::findOrCreate(
            'employee.view',
            'web',
        );

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee([
            'user_id' => $otherUser->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/employees/{$otherEmployee->id}",
            );

        $response->assertForbidden();
    }

    public function test_manager_can_only_list_direct_subordinates(): void
    {
        $managerUser = User::factory()->create();

        $role = Role::findOrCreate(
            'manager',
            'web',
        );

        $permission = Permission::findOrCreate(
            'employee.view',
            'web',
        );

        $role->givePermissionTo($permission);
        $managerUser->assignRole($role);

        $manager = $this->createEmployee([
            'user_id' => $managerUser->id,
        ]);

        $teamMember = $this->createEmployee([
            'manager_id' => $manager->id,
        ]);

        $otherManagerUser = User::factory()->create();

        $otherManager = $this->createEmployee([
            'user_id' => $otherManagerUser->id,
        ]);

        $otherTeamMember = $this->createEmployee([
            'manager_id' => $otherManager->id,
        ]);

        $response = $this
            ->actingAs($managerUser)
            ->getJson('/api/v1/employees');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $teamMember->id,
            );

        $this->assertNotEquals(
            $otherTeamMember->id,
            $response->json('data.0.id'),
        );
    }

    public function test_manager_cannot_show_employee_outside_team(): void
    {
        $managerUser = User::factory()->create();

        $role = Role::findOrCreate(
            'manager',
            'web',
        );

        $permission = Permission::findOrCreate(
            'employee.view',
            'web',
        );

        $role->givePermissionTo($permission);
        $managerUser->assignRole($role);

        $manager = $this->createEmployee([
            'user_id' => $managerUser->id,
        ]);

        $this->createEmployee([
            'manager_id' => $manager->id,
        ]);

        $otherManager = $this->createEmployee();

        $otherTeamMember = $this->createEmployee([
            'manager_id' => $otherManager->id,
        ]);

        $response = $this
            ->actingAs($managerUser)
            ->getJson(
                "/api/v1/employees/{$otherTeamMember->id}",
            );

        $response->assertForbidden();
    }

    public function test_manager_cannot_create_employee(): void
    {
        $user = $this->createUserWithRole(
            'manager',
            'employee.create',
        );

        $department = $this->createDepartment();
        $position = $this->createPosition();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/employees',
                $this->employeePayload(
                    $department,
                    $position,
                ),
            );

        $response->assertForbidden();
    }

    public function test_employee_cannot_create_employee(): void
    {
        $user = $this->createUserWithRole(
            'employee',
            'employee.create',
        );

        $department = $this->createDepartment();
        $position = $this->createPosition();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/employees',
                $this->employeePayload(
                    $department,
                    $position,
                ),
            );

        $response->assertForbidden();
    }

    public function test_hr_admin_can_view_sensitive_employee_data(): void
    {
        $hrAdmin = $this->createUserWithRole(
            'hr-admin',
            'employee.view',
        );

        $employee = $this->createEmployee([
            'gender' => 'male',
            'birth_date' => '1995-05-20',
            'phone' => '081234567890',
            'address' => 'Jakarta Selatan',
        ]);

        $response = $this
            ->actingAs($hrAdmin)
            ->getJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.birth_date', '1995-05-20')
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.address', 'Jakarta Selatan');
    }

    public function test_manager_cannot_view_sensitive_employee_data(): void
    {
        $manager = $this->createUserWithRole(
            'manager',
            'employee.view',
        );

        $managerEmployee = $this->createEmployee([
            'user_id' => $manager->id,
        ]);

        $employee = $this->createEmployee([
            'manager_id' => $managerEmployee->id,
            'gender' => 'male',
            'birth_date' => '1995-05-20',
            'phone' => '081234567890',
            'address' => 'Jakarta Selatan',
        ]);

        $response = $this
            ->actingAs($manager)
            ->getJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.full_name',
                trim("{$employee->first_name} {$employee->last_name}")
            )
            ->assertJsonMissingPath('data.gender')
            ->assertJsonMissingPath('data.birth_date')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.address');
    }

    public function test_employee_can_view_own_sensitive_data(): void
    {
        $user = $this->createUserWithRole(
            'employee',
            'employee.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
            'gender' => 'female',
            'birth_date' => '1998-10-15',
            'phone' => '081234567890',
            'address' => 'Bandung',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.birth_date', '1998-10-15')
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.address', 'Bandung');
    }

    public function test_manager_cannot_view_employee_account_data(): void
    {
        $manager = $this->createUserWithRole(
            'manager',
            'employee.view',
        );

        $managerEmployee = $this->createEmployee([
            'user_id' => $manager->id,
        ]);

        $employeeUser = User::factory()->create([
            'email' => 'employee@example.com',
        ]);

        $employee = $this->createEmployee([
            'user_id' => $employeeUser->id,
            'manager_id' => $managerEmployee->id,
        ]);

        $response = $this
            ->actingAs($manager)
            ->getJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.user.email')
            ->assertJsonMissingPath('data.user.is_active');
    }

    public function test_employee_can_view_own_account_data(): void
    {
        $user = $this->createUserWithRole(
            'employee',
            'employee.view',
        );

        $user->update([
            'email' => 'employee@example.com',
        ]);

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.user.email',
                'employee@example.com',
            );
    }

    public function test_employee_cannot_view_employment_history(): void
    {
        $user = $this->createUserWithRole(
            'employee',
            'employee.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/employees/{$employee->id}");

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'data.employment_histories',
            );
    }

    public function test_hr_admin_can_view_employment_history(): void
    {
        $hrAdmin = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

        $role = $hrAdmin->roles()->first();

        $role->givePermissionTo(
            Permission::findOrCreate(
                'employee.view',
                'web',
            ),
        );

        $department = $this->createDepartment();

        $position = $this->createPosition();

        $payload = $this->employeePayload(
            $department,
            $position,
        );

        $createResponse = $this
            ->actingAs($hrAdmin)
            ->postJson(
                '/api/v1/employees',
                $payload,
            );

        $createResponse->assertCreated();

        $employeeId = $createResponse->json('data.id');

        $response = $this
            ->actingAs($hrAdmin)
            ->getJson(
                "/api/v1/employees/{$employeeId}",
            );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'employment_histories',
                ],
            ]);
    }

    public function test_create_employee_rejects_inactive_department(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

        $department = $this->createDepartment([
            'is_active' => false,
            'status' => 'inactive',
        ]);

        $position = $this->createPosition();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/employees',
                $this->employeePayload(
                    $department,
                    $position,
                ),
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'department_id',
            ]);

        $this->assertDatabaseMissing('employees', [
            'department_id' => $department->id,
        ]);
    }

    public function test_update_employee_rejects_inactive_department(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

        $currentDepartment = $this->createDepartment([
            'code' => 'DEP-CURRENT',
        ]);

        $inactiveDepartment = $this->createDepartment([
            'code' => 'DEP-INACTIVE',
            'is_active' => false,
            'status' => 'inactive',
        ]);

        $position = $this->createPosition();

        $employee = $this->createEmployee(
            department: $currentDepartment,
            position: $position,
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'department_id' => $inactiveDepartment->id,
                    'effective_date' => now()->toDateString(),
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'department_id',
            ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'department_id' => $currentDepartment->id,
        ]);
    }

    public function test_create_employee_rejects_inactive_position(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

        $department = $this->createDepartment();

        $position = $this->createPosition([
            'is_active' => false,
            'status' => 'inactive',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/employees',
                $this->employeePayload(
                    $department,
                    $position,
                ),
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'position_id',
            ]);
    }

    public function test_update_employee_rejects_inactive_position(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

        $currentPosition = $this->createPosition([
            'code' => 'POS-CURRENT',
        ]);

        $inactivePosition = $this->createPosition([
            'code' => 'POS-INACTIVE',
            'is_active' => false,
            'status' => 'inactive',
        ]);

        $department = $this->createDepartment();

        $employee = $this->createEmployee(
            department: $department,
            position: $currentPosition,
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'position_id' => $inactivePosition->id,
                    'effective_date' => now()->toDateString(),
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'position_id',
            ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'position_id' => $currentPosition->id,
        ]);
    }

    public function test_update_employee_rejects_circular_manager_hierarchy(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee([
            'manager_id' => $employeeA->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employeeA->id}",
                [
                    'manager_id' => $employeeB->id,
                    'effective_date' => now()->toDateString(),
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'manager_id',
            ]);
    }

    public function test_update_employee_rejects_deep_circular_manager_hierarchy(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

        $employeeA = $this->createEmployee();

        $employeeB = $this->createEmployee([
            'manager_id' => $employeeA->id,
        ]);

        $employeeC = $this->createEmployee([
            'manager_id' => $employeeB->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employeeA->id}",
                [
                    'manager_id' => $employeeC->id,
                    'effective_date' => now()->toDateString(),
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'manager_id',
            ]);
    }

    public function test_update_employee_accepts_valid_manager_hierarchy(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

        $manager = $this->createEmployee();

        $employee = $this->createEmployee();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'manager_id' => $manager->id,
                    'effective_date' => now()->toDateString(),
                ],
            );

        $response->assertOk();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'manager_id' => $manager->id,
        ]);
    }

    public function test_employment_change_requires_effective_date(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.update',
        );

        $employee = $this->createEmployee();

        $newPosition = $this->createPosition();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'position_id' => $newPosition->id,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'effective_date',
            ]);
    }

    public function test_effective_date_cannot_be_before_current_history_start(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

        $role = $user->roles()->first();

        $role->givePermissionTo(
            Permission::findOrCreate(
                'employee.update',
                'web',
            ),
        );

        $department = $this->createDepartment();

        $oldPosition = $this->createPosition([
            'code' => 'POS-OLD',
        ]);

        $newPosition = $this->createPosition([
            'code' => 'POS-NEW',
        ]);

        $employee = $this->createEmployeeThroughApi(
            creator: $user,
            department: $department,
            position: $oldPosition,
            overrides: [
                'join_date' => '2025-01-01',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'position_id' => $newPosition->id,
                    'effective_date' => '2024-12-01',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'effective_date',
            ]);
    }

    public function test_update_position_creates_new_employment_history(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

        $role = $user->roles()->first();

        $role->givePermissionTo(
            Permission::findOrCreate(
                'employee.update',
                'web',
            ),
        );

        $department = $this->createDepartment();

        $oldPosition = $this->createPosition([
            'code' => 'POS-OLD',
        ]);

        $newPosition = $this->createPosition([
            'code' => 'POS-NEW',
        ]);

        $employee = $this->createEmployeeThroughApi(
            creator: $user,
            department: $department,
            position: $oldPosition,
            overrides: [
                'join_date' => '2025-01-01',
            ],
        );

        $effectiveDate = now()->toDateString();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'position_id' => $newPosition->id,
                    'effective_date' => $effectiveDate,
                    'history_reason' => 'Promotion',
                    'history_notes' => 'Promotion to new position.',
                ],
            );

        $response->assertOk();

        $employee->refresh();

        // Pastikan employee sudah menggunakan position baru.
        $this->assertSame(
            $newPosition->id,
            $employee->position_id,
        );

        // Pastikan history lama ditutup.
        $oldHistory = $employee
            ->employmentHistories()
            ->where('position_id', $oldPosition->id)
            ->first();

        $this->assertNotNull($oldHistory);

        $this->assertSame(
            $employee->id,
            $oldHistory->employee_id,
        );

        $this->assertSame(
            $department->id,
            $oldHistory->department_id,
        );

        $this->assertSame(
            $oldPosition->id,
            $oldHistory->position_id,
        );

        $this->assertSame(
            '2025-01-01',
            $oldHistory->start_date->toDateString(),
        );

        $this->assertSame(
            now()->subDay()->toDateString(),
            $oldHistory->end_date->toDateString(),
        );

        // Pastikan history baru dibuat sebagai current history.
        $newHistory = $employee
            ->employmentHistories()
            ->where('position_id', $newPosition->id)
            ->whereNull('end_date')
            ->first();

        $this->assertNotNull($newHistory);

        $this->assertSame(
            $employee->id,
            $newHistory->employee_id,
        );

        $this->assertSame(
            $department->id,
            $newHistory->department_id,
        );

        $this->assertSame(
            $newPosition->id,
            $newHistory->position_id,
        );

        $this->assertSame(
            $effectiveDate,
            $newHistory->start_date->toDateString(),
        );

        $this->assertNull(
            $newHistory->end_date,
        );

        $this->assertSame(
            'Promotion',
            $newHistory->reason,
        );

        $this->assertSame(
            'Promotion to new position.',
            $newHistory->notes,
        );
    }

    private function createEmployeeThroughApi(
        User $creator,
        Department $department,
        Position $position,
        array $overrides = [],
    ): Employee {
        $response = $this
            ->actingAs($creator)
            ->postJson(
                '/api/v1/employees',
                $this->employeePayload(
                    $department,
                    $position,
                    $overrides,
                ),
            );

        $response->assertCreated();

        return Employee::query()->findOrFail(
            $response->json('data.id'),
        );
    }

    public function test_update_employment_creates_new_employment_history(): void
    {
        $user = $this->createUserWithRole(
            'hr-admin',
            'employee.create',
        );

        $role = $user->roles()->first();

        $role->givePermissionTo(
            Permission::findOrCreate(
                'employee.update',
                'web',
            ),
        );

        $department = $this->createDepartment();

        $oldPosition = $this->createPosition([
            'code' => 'POS-OLD',
        ]);

        $newPosition = $this->createPosition([
            'code' => 'POS-NEW',
        ]);

        $employee = $this->createEmployeeThroughApi(
            creator: $user,
            department: $department,
            position: $oldPosition,
        );

        $effectiveDate = now()->toDateString();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/employees/{$employee->id}",
                [
                    'position_id' => $newPosition->id,
                    'effective_date' => $effectiveDate,
                    'history_reason' => 'Promotion',
                    'history_notes' => 'Promotion to senior position.',
                ],
            );

        $response->assertOk();

        $employee->refresh();

        $oldHistory = $employee
            ->employmentHistories()
            ->where('position_id', $oldPosition->id)
            ->first();

        $this->assertNotNull($oldHistory);

        $this->assertSame(
            '2025-01-01',
            $oldHistory->start_date->toDateString(),
        );

        $this->assertSame(
            now()->subDay()->toDateString(),
            $oldHistory->end_date->toDateString(),
        );

        $newHistory = $employee
            ->employmentHistories()
            ->where('position_id', $newPosition->id)
            ->whereNull('end_date')
            ->first();

        $this->assertNotNull($newHistory);

        $this->assertSame(
            $effectiveDate,
            $newHistory->start_date->toDateString(),
        );

        $this->assertSame(
            'Promotion',
            $newHistory->reason,
        );

        $this->assertSame(
            'Promotion to senior position.',
            $newHistory->notes,
        );
    }
}
