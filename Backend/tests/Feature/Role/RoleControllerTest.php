<?php

namespace Tests\Feature\Role;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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

    public function test_guest_cannot_access_roles(): void
    {
        $response = $this->getJson('/api/v1/roles');

        $response->assertUnauthorized();
    }

    public function test_user_without_role_view_permission_cannot_list_roles(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/roles');

        $response->assertForbidden();
    }

    public function test_user_with_role_view_permission_can_list_roles(): void
    {
        $user = $this->createUserWithPermissions([
            'role.view',
        ]);

        Role::query()->create([
            'name' => 'HR Manager',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/roles');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Daftar role berhasil diambil.',
            ]);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'guard_name',
                    'permissions',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
    }

    public function test_user_with_role_view_permission_can_show_role(): void
    {
        $user = $this->createUserWithPermissions([
            'role.view',
        ]);

        $role = Role::query()->create([
            'name' => 'HR Manager',
            'guard_name' => 'web',
        ]);

        $permission = Permission::query()->create([
            'name' => 'employee.view',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/roles/{$role->id}");

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Detail role berhasil diambil.',
                'data' => [
                    'id' => $role->id,
                    'name' => 'HR Manager',
                    'guard_name' => 'web',
                    'permissions' => [
                        'employee.view',
                    ],
                ],
            ]);
    }

    public function test_user_with_role_create_permission_can_create_role(): void
    {
        $user = $this->createUserWithPermissions([
            'role.create',
        ]);

        Permission::query()->create([
            'name' => 'employee.view',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/roles', [
                'name' => 'HR Manager',
                'permissions' => [
                    'employee.view',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Role berhasil dibuat.',
                'data' => [
                    'name' => 'HR Manager',
                    'guard_name' => 'web',
                    'permissions' => [
                        'employee.view',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'HR Manager',
            'guard_name' => 'web',
        ]);
    }

    public function test_user_without_role_create_permission_cannot_create_role(): void
    {
        $user = $this->createUserWithPermissions([
            'role.view',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/roles', [
                'name' => 'HR Manager',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('roles', [
            'name' => 'HR Manager',
        ]);
    }

    public function test_create_role_requires_name(): void
    {
        $user = $this->createUserWithPermissions([
            'role.create',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/roles', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);
    }

    public function test_create_role_rejects_duplicate_name(): void
    {
        $user = $this->createUserWithPermissions([
            'role.create',
        ]);

        Role::query()->create([
            'name' => 'HR Manager',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/roles', [
                'name' => 'HR Manager',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);
    }

    public function test_create_role_rejects_unknown_permission(): void
    {
        $user = $this->createUserWithPermissions([
            'role.create',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/roles', [
                'name' => 'HR Manager',
                'permissions' => [
                    'permission.does_not_exist',
                ],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'permissions.0',
            ]);
    }

    public function test_user_with_role_update_permission_can_update_role(): void
    {
        $user = $this->createUserWithPermissions([
            'role.update',
        ]);

        $role = Role::query()->create([
            'name' => 'HR Manager',
            'guard_name' => 'web',
        ]);

        Permission::query()->create([
            'name' => 'employee.view',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson("/api/v1/roles/{$role->id}", [
                'name' => 'HR Senior Manager',
                'permissions' => [
                    'employee.view',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Role berhasil diperbarui.',
                'data' => [
                    'id' => $role->id,
                    'name' => 'HR Senior Manager',
                    'guard_name' => 'web',
                    'permissions' => [
                        'employee.view',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'HR Senior Manager',
        ]);
    }

    public function test_user_without_role_update_permission_cannot_update_role(): void
    {
        $user = $this->createUserWithPermissions([
            'role.view',
        ]);

        $role = Role::query()->create([
            'name' => 'HR Manager',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson("/api/v1/roles/{$role->id}", [
                'name' => 'HR Senior Manager',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'HR Manager',
        ]);
    }

    public function test_user_with_role_delete_permission_can_delete_custom_role(): void
    {
        $user = $this->createUserWithPermissions([
            'role.delete',
        ]);

        $role = Role::query()->create([
            'name' => 'Custom Role',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/roles/{$role->id}");

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Role berhasil dihapus.',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_user_without_role_delete_permission_cannot_delete_role(): void
    {
        $user = $this->createUserWithPermissions([
            'role.view',
        ]);

        $role = Role::query()->create([
            'name' => 'Custom Role',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $user = $this->createUserWithPermissions([
            'role.delete',
        ]);

        $role = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertInternalServerError();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'admin',
        ]);
    }

    public function test_system_role_cannot_be_renamed(): void
    {
        $user = $this->createUserWithPermissions([
            'role.update',
        ]);

        $role = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson("/api/v1/roles/{$role->id}", [
                'name' => 'administrator',
            ]);

        $response->assertInternalServerError();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'admin',
        ]);
    }

    public function test_user_without_permission_can_not_access_permission_list(): void
    {
        $user = User::factory()->create();

        Permission::query()->create([
            'name' => 'permission.view',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/permissions');

        $response->assertForbidden();
    }

    public function test_user_with_permission_view_permission_can_list_permissions(): void
    {
        $user = $this->createUserWithPermissions([
            'permission.view',
        ]);

        Permission::query()->create([
            'name' => 'employee.view',
            'guard_name' => 'web',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/permissions');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Daftar permission berhasil diambil.',
            ]);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'guard_name',
                ],
            ],
        ]);
    }

    public function test_guest_cannot_access_permission_list(): void
    {
        $response = $this->getJson('/api/v1/permissions');

        $response->assertUnauthorized();
    }
}
