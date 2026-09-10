<?php

namespace Tests\Feature\Position;

use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PositionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPosition(array $overrides = []): Position
    {
        return Position::query()->create(
            $this->positionPayload($overrides),
        );
    }
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

    private function positionPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'POS-001',
            'name' => 'Software Engineer',
            'description' => 'Software Engineer Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ], $overrides);
    }

    public function test_guest_cannot_access_positions(): void
    {
        $response = $this->getJson('/api/v1/positions');

        $response->assertUnauthorized();
    }

    public function test_user_without_position_view_permission_cannot_list_positions(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/positions');

        $response->assertForbidden();
    }

    public function test_user_with_position_view_permission_can_list_positions(): void
    {
        $user = $this->createUserWithPermission('position.view');

        $this->createPosition([
            'code' => 'POS-001',
        ]);

        $this->createPosition([
            'code' => 'POS-002',
        ]);

        $this->createPosition([
            'code' => 'POS-003',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/positions');

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

    public function test_user_with_position_view_permission_can_search_positions(): void
    {
        $user = $this->createUserWithPermission('position.view');

        $this->createPosition([
            'code' => 'DEV-001',
            'name' => 'Software Developer',
            'description' => 'Development position',
        ]);

        $this->createPosition([
            'code' => 'HR-001',
            'name' => 'Human Resources',
            'description' => 'HR position',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/positions?search=developer');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Software Developer',
            );
    }

    private function createPositions(int $count): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $this->createPosition([
                'code' => sprintf('POS-%03d', $index),
            ]);
        }
    }

    public function test_position_list_supports_pagination(): void
    {
        $user = $this->createUserWithPermission('position.view');

        $this->createPositions(5);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/positions?per_page=2');

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

    public function test_user_with_position_view_permission_can_show_position(): void
    {
        $user = $this->createUserWithPermission('position.view');

        $position = $this->createPosition([
            'code' => 'POS-001',
            'name' => 'Software Engineer',
            'level' => 5,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/positions/{$position->id}");

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJsonPath(
                'data.id',
                $position->id,
            )
            ->assertJsonPath(
                'data.code',
                'POS-001',
            )
            ->assertJsonPath(
                'data.name',
                'Software Engineer',
            );
    }

    public function test_user_with_position_view_permission_gets_404_for_unknown_position(): void
    {
        $user = $this->createUserWithPermission('position.view');

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/positions/999999');

        $response->assertNotFound();
    }

    public function test_user_with_position_create_permission_can_create_position(): void
    {
        $user = $this->createUserWithPermission('position.create');

        $payload = $this->positionPayload();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/positions', $payload);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJsonPath(
                'data.code',
                'POS-001',
            )
            ->assertJsonPath(
                'data.name',
                'Software Engineer',
            )
            ->assertJsonPath(
                'data.level',
                5,
            );

        $this->assertDatabaseHas('positions', [
            'code' => 'POS-001',
            'name' => 'Software Engineer',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_user_without_position_create_permission_cannot_create_position(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/positions',
                $this->positionPayload(),
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing('positions', [
            'code' => 'POS-001',
        ]);
    }

    public function test_create_position_requires_code_name_level_and_status(): void
    {
        $user = $this->createUserWithPermission('position.create');

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/positions', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'name',
                'level',
                'status',
            ]);
    }

    public function test_create_position_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('position.create');

        $this->createPosition([
            'code' => 'POS-001',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/positions',
                $this->positionPayload([
                    'code' => 'POS-001',
                ]),
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_create_position_rejects_invalid_is_active(): void
    {
        $user = $this->createUserWithPermission('position.create');

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/positions',
                $this->positionPayload([
                    'is_active' => 'invalid',
                ]),
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
            ]);
    }

    public function test_user_with_position_update_permission_can_update_position(): void
    {
        $user = $this->createUserWithPermission('position.update');

        $position = $this->createPosition([
            'code' => 'POS-001',
            'name' => 'Software Engineer',
            'level' => 5,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/positions/{$position->id}",
                [
                    'name' => 'Senior Software Engineer',
                    'level' => 6,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Senior Software Engineer',
            )
            ->assertJsonPath(
                'data.level',
                6,
            );

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'name' => 'Senior Software Engineer',
            'level' => 6,
        ]);
    }

    public function test_user_without_position_update_permission_cannot_update_position(): void
    {
        $user = User::factory()->create();

        $position = $this->createPosition([
            'name' => 'Software Engineer',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/positions/{$position->id}",
                [
                    'name' => 'Senior Software Engineer',
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'name' => 'Software Engineer',
        ]);
    }

    public function test_update_position_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('position.update');

        $firstPosition = $this->createPosition([
            'code' => 'POS-001',
        ]);

        $secondPosition = $this->createPosition([
            'code' => 'POS-002',
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/positions/{$secondPosition->id}",
                [
                    'code' => $firstPosition->code,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_user_with_position_delete_permission_can_delete_position(): void
    {
        $user = $this->createUserWithPermission('position.delete');

        $position = $this->createPosition();

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/v1/positions/{$position->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data',
                null,
            );

        $this->assertSoftDeleted('positions', [
            'id' => $position->id,
        ]);
    }

    public function test_user_without_position_delete_permission_cannot_delete_position(): void
    {
        $user = User::factory()->create();

        $position = $this->createPosition();

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/v1/positions/{$position->id}",
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('positions', [
            'id' => $position->id,
            'deleted_at' => null,
        ]);
    }

    public function test_guest_cannot_create_position(): void
    {
        $response = $this->postJson(
            '/api/v1/positions',
            $this->positionPayload(),
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_update_position(): void
    {
        $position = $this->createPosition();

        $response = $this->putJson(
            "/api/v1/positions/{$position->id}",
            [
                'name' => 'Updated Position',
            ],
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_delete_position(): void
    {
        $position = $this->createPosition();

        $response = $this->deleteJson(
            "/api/v1/positions/{$position->id}",
        );

        $response->assertUnauthorized();
    }
}
