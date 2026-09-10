<?php

namespace Tests\Feature\Department;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithPermissions(
        array $permissions,
    ): User {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'test-role-' . uniqid(),
            'guard_name' => 'web',
        ]);

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }

    public function test_guest_cannot_access_departments(): void
    {
        $response = $this->getJson('/api/v1/departments');

        $response->assertUnauthorized();
    }

    public function test_user_without_department_view_permission_cannot_list_departments(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/departments');

        $response->assertForbidden();
    }

    public function test_user_with_department_view_permission_can_list_departments(): void
    {
        $user = $this->createUserWithPermissions([
            'department.view',
        ]);

        Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'Department Human Resources.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/departments');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Daftar department berhasil diambil.',
            ]);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'code',
                    'name',
                    'description',
                    'status',
                    'is_active',
                    'created_at',
                    'updated_at',
                ],
            ],
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

    public function test_user_with_department_view_permission_can_search_departments(): void
    {
        $user = $this->createUserWithPermissions([
            'department.view',
        ]);

        Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'Department Human Resources.',
            'status' => 'active',
            'is_active' => true,
        ]);

        Department::query()->create([
            'code' => 'FIN',
            'name' => 'Finance',
            'description' => 'Department Finance.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/departments?search=Human');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.name',
            'Human Resources',
        );
    }

    public function test_department_list_supports_pagination(): void
    {
        $user = $this->createUserWithPermissions([
            'department.view',
        ]);

        foreach (range(1, 20) as $number) {
            Department::query()->create([
                'code' => 'DEP-' . $number,
                'name' => 'Department ' . $number,
                'description' => 'Description ' . $number,
                'status' => 'active',
                'is_active' => true,
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/departments?per_page=5');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                5,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                20,
            );

        $this->assertCount(
            5,
            $response->json('data'),
        );
    }

    public function test_user_with_department_view_permission_can_show_department(): void
    {
        $user = $this->createUserWithPermissions([
            'department.view',
        ]);

        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'Department Human Resources.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/departments/{$department->id}");

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Detail department berhasil diambil.',
                'data' => [
                    'id' => $department->id,
                    'code' => 'HR',
                    'name' => 'Human Resources',
                    'description' => 'Department Human Resources.',
                    'status' => 'active',
                    'is_active' => true,
                ],
            ]);
    }

    public function test_user_with_department_view_permission_gets_404_for_unknown_department(): void
    {
        $user = $this->createUserWithPermissions([
            'department.view',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/departments/999999');

        $response->assertNotFound();
    }

    public function test_user_with_department_create_permission_can_create_department(): void
    {
        $user = $this->createUserWithPermissions([
            'department.create',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/departments', [
                'code' => 'HR',
                'name' => 'Human Resources',
                'description' => 'Department Human Resources.',
                'status' => 'active',
                'is_active' => true,
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Department berhasil dibuat.',
                'data' => [
                    'code' => 'HR',
                    'name' => 'Human Resources',
                    'description' => 'Department Human Resources.',
                    'status' => 'active',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('departments', [
            'code' => 'HR',
            'name' => 'Human Resources',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_user_without_department_create_permission_cannot_create_department(): void
    {
        $user = $this->createUserWithPermissions([
            'department.view',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/departments', [
                'code' => 'HR',
                'name' => 'Human Resources',
                'status' => 'active',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('departments', [
            'code' => 'HR',
        ]);
    }

    public function test_create_department_requires_code_name_and_status(): void
    {
        $user = $this->createUserWithPermissions([
            'department.create',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/departments', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'name',
                'status',
            ]);
    }

    public function test_create_department_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermissions([
            'department.create',
        ]);

        Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/departments', [
                'code' => 'HR',
                'name' => 'Human Resources 2',
                'status' => 'active',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_create_department_rejects_invalid_is_active(): void
    {
        $user = $this->createUserWithPermissions([
            'department.create',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/departments', [
                'code' => 'HR',
                'name' => 'Human Resources',
                'status' => 'active',
                'is_active' => 'invalid',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
            ]);
    }

    public function test_user_with_department_update_permission_can_update_department(): void
    {
        $user = $this->createUserWithPermissions([
            'department.update',
        ]);

        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'Old description.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson("/api/v1/departments/{$department->id}", [
                'name' => 'Human Resources Updated',
                'description' => 'Updated description.',
                'status' => 'inactive',
                'is_active' => false,
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Department berhasil diperbarui.',
                'data' => [
                    'id' => $department->id,
                    'code' => 'HR',
                    'name' => 'Human Resources Updated',
                    'description' => 'Updated description.',
                    'status' => 'inactive',
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Human Resources Updated',
            'status' => 'inactive',
            'is_active' => false,
        ]);
    }

    public function test_user_without_department_update_permission_cannot_update_department(): void
    {
        $user = $this->createUserWithPermissions([
            'department.view',
        ]);

        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson("/api/v1/departments/{$department->id}", [
                'name' => 'Updated Department',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Human Resources',
        ]);
    }

    public function test_update_department_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermissions([
            'department.update',
        ]);

        $firstDepartment = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        Department::query()->create([
            'code' => 'FIN',
            'name' => 'Finance',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson("/api/v1/departments/{$firstDepartment->id}", [
                'code' => 'FIN',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_user_with_department_delete_permission_can_delete_department(): void
    {
        $user = $this->createUserWithPermissions([
            'department.delete',
        ]);

        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/departments/{$department->id}");

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Department berhasil dihapus.',
                'data' => null,
            ]);

        $this->assertSoftDeleted('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_user_without_department_delete_permission_cannot_delete_department(): void
    {
        $user = $this->createUserWithPermissions([
            'department.view',
        ]);

        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/departments/{$department->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
        ]);
    }

    public function test_guest_cannot_create_department(): void
    {
        $response = $this->postJson('/api/v1/departments', [
            'code' => 'HR',
            'name' => 'Human Resources',
            'status' => 'active',
        ]);

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_update_department(): void
    {
        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->putJson(
            "/api/v1/departments/{$department->id}",
            [
                'name' => 'Updated Department',
            ],
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_delete_department(): void
    {
        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/departments/{$department->id}");

        $response->assertUnauthorized();
    }
}
