<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_guest_cannot_list_leave_types(): void
    {
        $response = $this->getJson('/api/v1/leave-types');

        $response->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_list_leave_types(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types');

        $response->assertForbidden();
    }

    public function test_user_with_view_permission_can_list_leave_types(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        LeaveType::query()->create([
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL',
            'default_days' => 12,
            'description' => 'Cuti tahunan karyawan.',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Daftar Leave Type berhasil diambil.',
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

    public function test_leave_type_list_can_search_by_name(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        LeaveType::query()->create([
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL',
            'default_days' => 12,
            'description' => 'Cuti tahunan.',
            'status' => 'active',
        ]);

        LeaveType::query()->create([
            'name' => 'Cuti Sakit',
            'code' => 'SICK',
            'default_days' => 12,
            'description' => 'Cuti sakit.',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types?search=Tahunan');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cuti Tahunan');
    }

    public function test_leave_type_list_can_search_by_code(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        LeaveType::query()->create([
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL',
            'default_days' => 12,
            'description' => null,
            'status' => 'active',
        ]);

        LeaveType::query()->create([
            'name' => 'Cuti Sakit',
            'code' => 'SICK',
            'default_days' => 12,
            'description' => null,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types?search=SICK');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SICK');
    }

    public function test_leave_type_list_can_search_by_description(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        LeaveType::query()->create([
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL',
            'default_days' => 12,
            'description' => 'Digunakan untuk kebutuhan liburan.',
            'status' => 'active',
        ]);

        LeaveType::query()->create([
            'name' => 'Cuti Sakit',
            'code' => 'SICK',
            'default_days' => 12,
            'description' => 'Digunakan ketika karyawan sakit.',
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types?search=liburan');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ANNUAL');
    }

    public function test_leave_type_list_can_paginate(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        foreach (range(1, 3) as $index) {
            LeaveType::query()->create([
                'name' => "Leave Type {$index}",
                'code' => "LEAVE-{$index}",
                'default_days' => 10,
                'description' => null,
                'status' => 'active',
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_leave_type_list_rejects_invalid_per_page(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types?per_page=101');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'per_page',
        ]);
    }

    public function test_leave_type_list_rejects_invalid_search_length(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types?search=' . str_repeat('a', 101));

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'search',
        ]);
    }

    public function test_guest_cannot_create_leave_type(): void
    {
        $response = $this
            ->postJson('/api/v1/leave-types', [
                'name' => 'Cuti Tahunan',
                'code' => 'ANNUAL',
                'default_days' => 12,
                'description' => 'Cuti tahunan.',
                'status' => 'active',
            ]);

        $response->assertUnauthorized();
    }

    public function test_user_without_create_permission_cannot_create_leave_type(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-types', [
                'name' => 'Cuti Tahunan',
                'code' => 'ANNUAL',
                'default_days' => 12,
                'description' => 'Cuti tahunan.',
                'status' => 'active',
            ]);

        $response->assertForbidden();
    }

    public function test_user_with_create_permission_can_create_leave_type(): void
    {
        $user = $this->createUserWithPermission('leave_type.create');

        $payload = [
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL',
            'default_days' => 12,
            'description' => 'Cuti tahunan karyawan.',
            'status' => 'active',
        ];

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-types', $payload);

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Type berhasil dibuat.',
            )
            ->assertJsonPath('data.name', 'Cuti Tahunan')
            ->assertJsonPath('data.code', 'ANNUAL')
            ->assertJsonPath('data.default_days', 12)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('leave_types', [
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL',
            'default_days' => 12,
            'description' => 'Cuti tahunan karyawan.',
            'status' => 'active',
        ]);
    }

    public function test_create_leave_type_rejects_missing_required_fields(): void
    {
        $user = $this->createUserWithPermission('leave_type.create');

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-types', []);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
            'code',
            'status',
        ]);
    }

    public function test_create_leave_type_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('leave_type.create');

        LeaveType::query()->create([
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL',
            'default_days' => 12,
            'description' => null,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-types', [
                'name' => 'Cuti Tahunan Baru',
                'code' => 'ANNUAL',
                'default_days' => 10,
                'description' => null,
                'status' => 'active',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'code',
        ]);
    }

    public function test_create_leave_type_rejects_invalid_default_days(): void
    {
        $user = $this->createUserWithPermission('leave_type.create');

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-types', [
                'name' => 'Cuti Tahunan',
                'code' => 'ANNUAL',
                'default_days' => -1,
                'description' => null,
                'status' => 'active',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'default_days',
        ]);
    }

    public function test_create_leave_type_rejects_invalid_status(): void
    {
        $user = $this->createUserWithPermission('leave_type.create');

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-types', [
                'name' => 'Cuti Tahunan',
                'code' => 'ANNUAL',
                'default_days' => 12,
                'description' => null,
                'status' => 'pending',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'status',
        ]);
    }

    public function test_guest_cannot_show_leave_type(): void
    {
        $leaveType = $this->createLeaveType();

        $response = $this->getJson(
            "/api/v1/leave-types/{$leaveType->id}",
        );

        $response->assertUnauthorized();
    }

    public function test_user_with_view_permission_can_show_leave_type(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/leave-types/{$leaveType->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Detail Leave Type berhasil diambil.',
            )
            ->assertJsonPath('data.id', $leaveType->id)
            ->assertJsonPath('data.code', $leaveType->code);
    }

    public function test_show_unknown_leave_type_returns_404(): void
    {
        $user = $this->createUserWithPermission('leave_type.view');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-types/999999');

        $response->assertNotFound();
    }

    public function test_user_without_update_permission_cannot_update_leave_type(): void
    {
        $user = $this->createUser();

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/leave-types/{$leaveType->id}",
                [
                    'name' => 'Cuti Tahunan Updated',
                ],
            );

        $response->assertForbidden();
    }

    public function test_user_with_update_permission_can_update_leave_type(): void
    {
        $user = $this->createUserWithPermission('leave_type.update');

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/leave-types/{$leaveType->id}",
                [
                    'name' => 'Cuti Tahunan Updated',
                    'default_days' => 15,
                    'status' => 'inactive',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Type berhasil diperbarui.',
            )
            ->assertJsonPath(
                'data.name',
                'Cuti Tahunan Updated',
            )
            ->assertJsonPath('data.default_days', 15)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'name' => 'Cuti Tahunan Updated',
            'default_days' => 15,
            'status' => 'inactive',
        ]);
    }


    public function test_update_leave_type_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('leave_type.update');

        $leaveType = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        LeaveType::query()->create([
            'name' => 'Cuti Sakit',
            'code' => 'SICK',
            'default_days' => 12,
            'description' => null,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/leave-types/{$leaveType->id}",
                [
                    'code' => 'SICK',
                ],
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'code',
        ]);
    }

    public function test_update_leave_type_rejects_invalid_status(): void
    {
        $user = $this->createUserWithPermission('leave_type.update');

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/leave-types/{$leaveType->id}",
                [
                    'status' => 'pending',
                ],
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'status',
        ]);
    }

    public function test_guest_cannot_delete_leave_type(): void
    {
        $leaveType = $this->createLeaveType();

        $response = $this->deleteJson(
            "/api/v1/leave-types/{$leaveType->id}",
        );

        $response->assertUnauthorized();
    }

    public function test_user_without_delete_permission_cannot_delete_leave_type(): void
    {
        $user = $this->createUser();

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/v1/leave-types/{$leaveType->id}",
            );

        $response->assertForbidden();
    }

    public function test_user_with_delete_permission_can_delete_leave_type(): void
    {
        $user = $this->createUserWithPermission('leave_type.delete');

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/v1/leave-types/{$leaveType->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Type berhasil dihapus.',
            )
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('leave_types', [
            'id' => $leaveType->id,
        ]);
    }

    public function test_delete_unknown_leave_type_returns_404(): void
    {
        $user = $this->createUserWithPermission('leave_type.delete');

        $response = $this
            ->actingAs($user)
            ->deleteJson('/api/v1/leave-types/999999');

        $response->assertNotFound();
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
}
