<?php

namespace Tests\Feature\Position;

use App\Models\Department;
use App\Models\Position;
use App\Models\PositionRequirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PositionRequirementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPermission(string $permission): void
    {
        Permission::findOrCreate($permission, 'web');
    }

    private function createUserWithPermission(string $permission): User
    {
        $this->createPermission($permission);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => fake()->unique()->numerify('DEP####'),
            'name' => 'Test Department',
            'description' => 'Department for testing.',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(
        ?string $code = null,
        ?string $name = null,
    ): Position {
        $department = $this->createDepartment();

        return Position::query()->create([
            'code' => $code
                ?? fake()->unique()->numerify('POS####'),
            'name' => $name ?? 'Test Position',
            'description' => 'Position for testing.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPositionRequirement(
        ?Position $position = null,
        array $attributes = [],
    ): PositionRequirement {
        $position ??= $this->createPosition();

        return PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 2.00,
            'minimum_performance_score' => 70.00,
            'minimum_attendance_percentage' => 90.00,
            'description' => 'Test position requirement.',
            'status' => 'active',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_index(): void
    {
        $this->getJson('/api/v1/position-requirements')
            ->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_access_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements')
            ->assertForbidden();
    }

    public function test_user_can_list_position_requirements(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $requirement->id,
                'position_id' => $requirement->position_id,
            ]);
    }

    public function test_index_uses_default_pagination(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        for ($i = 0; $i < 3; $i++) {
            $this->createPositionRequirement();
        }

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 15);
    }

    public function test_index_supports_custom_pagination(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        for ($i = 0; $i < 5; $i++) {
            $this->createPositionRequirement();
        }

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 5);
    }

    public function test_index_clamps_per_page_to_minimum_one(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $this->createPositionRequirement();

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 1);
    }

    public function test_index_clamps_per_page_to_maximum_one_hundred(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $this->createPositionRequirement();

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements?per_page=200')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_index_can_search_by_position_code(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $position = $this->createPosition(
            code: 'POSSEARCH01',
            name: 'Manager',
        );

        $requirement = $this->createPositionRequirement(
            position: $position,
        );

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements?search=POSSEARCH01')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $requirement->id,
            ]);
    }

    public function test_index_can_search_by_position_name(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $position = $this->createPosition(
            code: 'POSSEARCH02',
            name: 'Senior Backend Developer',
        );

        $requirement = $this->createPositionRequirement(
            position: $position,
        );

        $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirements?search=Senior Backend Developer',
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $requirement->id,
            ]);
    }

    public function test_index_ignores_empty_search(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements?search=%20')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $requirement->id,
            ]);
    }

    public function test_index_can_filter_by_position_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $positionA = $this->createPosition();
        $positionB = $this->createPosition();

        $requirementA = $this->createPositionRequirement(
            position: $positionA,
        );

        $requirementB = $this->createPositionRequirement(
            position: $positionB,
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirements?position_id='
                    . $positionA->id,
            )
            ->assertOk();

        $response->assertJsonFragment([
            'id' => $requirementA->id,
        ]);

        $response->assertJsonMissing([
            'id' => $requirementB->id,
        ]);
    }

    public function test_user_can_show_position_requirement(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->getJson(
                "/api/v1/position-requirements/{$requirement->id}",
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $requirement->id,
                'position_id' => $requirement->position_id,
            ]);
    }

    public function test_show_returns_not_found_for_invalid_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirements/999999')
            ->assertNotFound();
    }

    public function test_user_can_get_requirement_by_position(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $position = $this->createPosition();

        $requirement = $this->createPositionRequirement(
            position: $position,
        );

        $this->actingAs($user)
            ->getJson(
                "/api/v1/positions/{$position->id}/requirements",
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $requirement->id,
                'position_id' => $position->id,
            ]);
    }

    public function test_by_position_only_returns_active_requirement(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $position = $this->createPosition();

        $this->createPositionRequirement(
            position: $position,
            attributes: [
                'is_active' => false,
            ],
        );

        $this->actingAs($user)
            ->getJson(
                "/api/v1/positions/{$position->id}/requirements",
            )
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath(
                'message',
                'Position belum memiliki requirement aktif.',
            );
    }

    public function test_by_position_returns_null_when_requirement_does_not_exist(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $position = $this->createPosition();

        $this->actingAs($user)
            ->getJson(
                "/api/v1/positions/{$position->id}/requirements",
            )
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath(
                'message',
                'Position belum memiliki requirement aktif.',
            );
    }

    public function test_by_position_returns_latest_active_requirement(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.view',
        );

        $position = $this->createPosition();

        $first = $this->createPositionRequirement(
            position: $position,
        );

        $second = $this->createPositionRequirement(
            position: $position,
            attributes: [
                'description' => 'Latest requirement.',
            ],
        );

        $response = $this->actingAs($user)
            ->getJson(
                "/api/v1/positions/{$position->id}/requirements",
            )
            ->assertOk();

        $response->assertJsonPath(
            'data.id',
            $second->id,
        );

        $response->assertJsonPath(
            'data.description',
            'Latest requirement.',
        );

        $response->assertJsonPath(
            'data.position_id',
            $position->id,
        );
    }

    public function test_user_can_create_position_requirement(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement.create',
        );

        $position = $this->createPosition();

        $payload = [
            'position_id' => $position->id,
            'minimum_experience_years' => 3,
            'minimum_performance_score' => 75,
            'minimum_attendance_percentage' => 90,
            'description' => 'Requirement for testing.',
            'status' => 'active',
            'is_active' => true,
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', $payload)
            ->assertCreated()
            ->assertJsonFragment([
                'position_id' => $position->id,
                'description' => 'Requirement for testing.',
                'status' => 'active',
            ]);

        $this->assertDatabaseHas('position_requirements', [
            'position_id' => $position->id,
            'status' => 'active',
        ]);
    }

    public function test_store_requires_position_id(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'minimum_experience_years' => 2,
                'status' => 'active',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
    }

    public function test_store_requires_minimum_experience_years(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $position = $this->createPosition();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => $position->id,
                'status' => 'active',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'minimum_experience_years',
            ]);
    }

    public function test_store_requires_status(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $position = $this->createPosition();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => $position->id,
                'minimum_experience_years' => 2,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_rejects_non_existing_position(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => 999999,
                'minimum_experience_years' => 2,
                'status' => 'active',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
    }

    public function test_store_rejects_negative_experience(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $position = $this->createPosition();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => $position->id,
                'minimum_experience_years' => -1,
                'status' => 'active',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'minimum_experience_years',
            ]);
    }

    public function test_store_rejects_experience_above_maximum(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $position = $this->createPosition();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => $position->id,
                'minimum_experience_years' => 1000,
                'status' => 'active',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'minimum_experience_years',
            ]);
    }

    public function test_store_rejects_performance_score_above_100(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $position = $this->createPosition();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => $position->id,
                'minimum_experience_years' => 2,
                'minimum_performance_score' => 101,
                'status' => 'active',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'minimum_performance_score',
            ]);
    }

    public function test_store_rejects_attendance_percentage_above_100(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $position = $this->createPosition();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => $position->id,
                'minimum_experience_years' => 2,
                'minimum_attendance_percentage' => 101,
                'status' => 'active',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'minimum_attendance_percentage',
            ]);
    }

    public function test_user_without_create_permission_cannot_create(): void
    {
        $user = User::factory()->create();

        $position = $this->createPosition();

        $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => $position->id,
                'minimum_experience_years' => 2,
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_user_can_update_position_requirement(): void
    {
        $user = $this->createUserWithPermission('position_requirement.update');

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->putJson(
                "/api/v1/position-requirements/{$requirement->id}",
                [
                    'minimum_experience_years' => 5,
                    'minimum_performance_score' => 80,
                    'description' => 'Updated requirement.',
                ],
            )
            ->assertOk()
            ->assertJsonFragment([
                'minimum_experience_years' => '5.00',
                'description' => 'Updated requirement.',
            ]);
    }

    public function test_user_can_partially_update_position_requirement(): void
    {
        $user = $this->createUserWithPermission('position_requirement.update');

        $requirement = $this->createPositionRequirement(
            attributes: [
                'minimum_experience_years' => 2,
                'description' => 'Original description.',
            ],
        );

        $this->actingAs($user)
            ->patchJson(
                "/api/v1/position-requirements/{$requirement->id}",
                [
                    'description' => 'Partial update.',
                ],
            )
            ->assertOk()
            ->assertJsonFragment([
                'description' => 'Partial update.',
            ]);

        $this->assertDatabaseHas('position_requirements', [
            'id' => $requirement->id,
            'minimum_experience_years' => 2,
            'description' => 'Partial update.',
        ]);
    }

    public function test_user_without_update_permission_cannot_update(): void
    {
        $user = User::factory()->create();

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->putJson(
                "/api/v1/position-requirements/{$requirement->id}",
                [
                    'description' => 'Unauthorized update.',
                ],
            )
            ->assertForbidden();
    }

    public function test_update_returns_not_found_for_invalid_id(): void
    {
        $user = $this->createUserWithPermission('position_requirement.update');

        $this->actingAs($user)
            ->putJson(
                '/api/v1/position-requirements/999999',
                [
                    'description' => 'Updated.',
                ],
            )
            ->assertNotFound();
    }

    public function test_user_can_delete_position_requirement(): void
    {
        $user = $this->createUserWithPermission('position_requirement.delete');

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->deleteJson("/api/v1/position-requirements/{$requirement->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted(
            'position_requirements',
            ['id' => $requirement->id],
        );
    }

    public function test_user_without_delete_permission_cannot_delete(): void
    {
        $user = User::factory()->create();

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->deleteJson("/api/v1/position-requirements/{$requirement->id}")
            ->assertForbidden();
    }

    public function test_delete_returns_not_found_for_invalid_id(): void
    {
        $user = $this->createUserWithPermission('position_requirement.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/position-requirements/999999')
            ->assertNotFound();
    }

    public function test_store_returns_created_status(): void
    {
        $user = $this->createUserWithPermission('position_requirement.create');

        $position = $this->createPosition();

        $this->actingAs($user)
            ->postJson('/api/v1/position-requirements', [
                'position_id' => $position->id,
                'minimum_experience_years' => 2,
                'status' => 'active',
            ])
            ->assertCreated();
    }

    public function test_resource_contains_position_data(): void
    {
        $user = $this->createUserWithPermission('position_requirement.view');

        $position = $this->createPosition(
            code: 'POSRESOURCE',
            name: 'Resource Position',
        );

        $requirement = $this->createPositionRequirement(
            position: $position
        );

        $this->actingAs($user)
            ->getJson(
                "/api/v1/position-requirements/{$requirement->id}",
            )
            ->assertOk()
            ->assertJsonPath('data.position.id', $position->id)
            ->assertJsonPath('data.position.code', 'POSRESOURCE')
            ->assertJsonPath('data.position.name', 'Resource Position')
            ->assertJsonPath('data.position.level', 1);
    }

    public function test_soft_deleted_requirement_is_not_returned_by_show(): void
    {
        $user = $this->createUserWithPermission('position_requirement.view');

        $requirement = $this->createPositionRequirement();

        $requirement->delete();

        $this->actingAs($user)
            ->getJson("/api/v1/position-requirements/{$requirement->id}")
            ->assertNotFound();
    }
}
