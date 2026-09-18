<?php

namespace Tests\Feature\Career;

use App\Models\CareerPath;
use App\Models\CareerPathPosition;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CareerPathPositionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'career_path_position.view',
            'career_path_position.create',
            'career_path_position.update',
            'career_path_position.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::create([
            'name' => 'career-path-position-tester',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permissions);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createCareerPath(array $overrides = []): CareerPath
    {
        return CareerPath::query()->create(array_merge([
            'code' => 'CP-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Engineering Career Path',
            'description' => 'Career path for engineering roles.',
            'status' => 'active',
            'is_active' => true,
        ], $overrides));
    }

    private function createPosition(array $overrides = []): Position
    {
        return Position::query()->create(array_merge([
            'code' => 'POS-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Software Engineer',
            'description' => 'Software Engineer Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ], $overrides));
    }

    private function createCareerPathPosition(
        ?CareerPath $careerPath = null,
        ?Position $position = null,
        array $overrides = [],
    ): CareerPathPosition {
        $careerPath ??= $this->createCareerPath();
        $position ??= $this->createPosition();

        return CareerPathPosition::query()->create(array_merge([
            'career_path_id' => $careerPath->id,
            'position_id' => $position->id,
            'sequence' => 1,
        ], $overrides));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/career-path-positions')
            ->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/career-path-positions')
            ->assertForbidden();
    }

    public function test_index_can_list_career_path_positions(): void
    {
        $item = $this->createCareerPathPosition();

        $this->actingAs($this->user)
            ->getJson('/api/v1/career-path-positions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.position_id', $item->position_id)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_index_can_filter_by_career_path(): void
    {
        $careerPath = $this->createCareerPath();
        $this->createCareerPathPosition($careerPath);
        $this->createCareerPathPosition($this->createCareerPath());

        $this->actingAs($this->user)
            ->getJson("/api/v1/career-path-positions?career_path_id={$careerPath->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.career_path_id', $careerPath->id);
    }

    public function test_index_can_filter_by_position(): void
    {
        $position = $this->createPosition();
        $this->createCareerPathPosition(null, $position);
        $this->createCareerPathPosition();

        $this->actingAs($this->user)
            ->getJson("/api/v1/career-path-positions?position_id={$position->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.position_id', $position->id);
    }

    public function test_index_supports_pagination(): void
    {
        $this->createCareerPathPosition();
        $this->createCareerPathPosition();
        $this->createCareerPathPosition();

        $this->actingAs($this->user)
            ->getJson('/api/v1/career-path-positions?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_index_clamps_per_page_to_maximum_100(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/career-path-positions?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_index_clamps_per_page_to_minimum_1(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/career-path-positions?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 1);
    }

    public function test_show_can_display_career_path_position(): void
    {
        $item = $this->createCareerPathPosition();

        $this->actingAs($this->user)
            ->getJson("/api/v1/career-path-positions/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.position.id', $item->position_id);
    }

    public function test_show_returns_not_found_for_missing_item(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/career-path-positions/999999')
            ->assertNotFound();
    }

    public function test_store_requires_permission(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/career-path-positions', [
                'career_path_id' => $careerPath->id,
                'position_id' => $position->id,
                'sequence' => 1,
            ])
            ->assertForbidden();
    }

    public function test_store_can_create_item(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();

        $this->actingAs($this->user)
            ->postJson('/api/v1/career-path-positions', [
                'career_path_id' => $careerPath->id,
                'position_id' => $position->id,
                'sequence' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.career_path_id', $careerPath->id)
            ->assertJsonPath('data.position_id', $position->id)
            ->assertJsonPath('data.sequence', 1);

        $this->assertDatabaseHas('career_path_positions', [
            'career_path_id' => $careerPath->id,
            'position_id' => $position->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/career-path-positions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'career_path_id',
                'position_id',
                'sequence',
            ]);
    }

    public function test_store_rejects_non_existing_career_path(): void
    {
        $position = $this->createPosition();

        $this->actingAs($this->user)
            ->postJson('/api/v1/career-path-positions', [
                'career_path_id' => 999999,
                'position_id' => $position->id,
                'sequence' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['career_path_id']);
    }

    public function test_store_rejects_non_existing_position(): void
    {
        $careerPath = $this->createCareerPath();

        $this->actingAs($this->user)
            ->postJson('/api/v1/career-path-positions', [
                'career_path_id' => $careerPath->id,
                'position_id' => 999999,
                'sequence' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
    }

    public function test_store_rejects_invalid_sequence(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();

        $this->actingAs($this->user)
            ->postJson('/api/v1/career-path-positions', [
                'career_path_id' => $careerPath->id,
                'position_id' => $position->id,
                'sequence' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sequence']);
    }

    public function test_store_rejects_duplicate_position_in_same_career_path(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();
        $this->createCareerPathPosition($careerPath, $position);

        $this->actingAs($this->user)
            ->postJson('/api/v1/career-path-positions', [
                'career_path_id' => $careerPath->id,
                'position_id' => $position->id,
                'sequence' => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
    }

    public function test_same_position_can_exist_in_different_career_paths(): void
    {
        $position = $this->createPosition();
        $this->createCareerPathPosition($this->createCareerPath(), $position);

        $secondCareerPath = $this->createCareerPath();

        $this->actingAs($this->user)
            ->postJson('/api/v1/career-path-positions', [
                'career_path_id' => $secondCareerPath->id,
                'position_id' => $position->id,
                'sequence' => 1,
            ])
            ->assertCreated();
    }

    public function test_update_requires_permission(): void
    {
        $item = $this->createCareerPathPosition();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson("/api/v1/career-path-positions/{$item->id}", [
                'sequence' => 2,
            ])
            ->assertForbidden();
    }

    public function test_update_can_update_item(): void
    {
        $item = $this->createCareerPathPosition();

        $this->actingAs($this->user)
            ->putJson("/api/v1/career-path-positions/{$item->id}", [
                'sequence' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.sequence', 2);
    }

    public function test_update_supports_partial_update(): void
    {
        $item = $this->createCareerPathPosition(
            null,
            null,
            ['sequence' => 3],
        );

        $this->actingAs($this->user)
            ->putJson("/api/v1/career-path-positions/{$item->id}", [
                'is_target' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.sequence', 3);
    }

    public function test_update_rejects_duplicate_position_in_same_career_path(): void
    {
        $careerPath = $this->createCareerPath();
        $firstPosition = $this->createPosition();
        $secondPosition = $this->createPosition();

        $this->createCareerPathPosition($careerPath, $firstPosition);
        $item = $this->createCareerPathPosition(
            $careerPath,
            $secondPosition,
            ['sequence' => 2],
        );

        $this->actingAs($this->user)
            ->putJson("/api/v1/career-path-positions/{$item->id}", [
                'position_id' => $firstPosition->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['position_id']);
    }

    public function test_update_allows_same_position(): void
    {
        $careerPath = $this->createCareerPath();
        $position = $this->createPosition();
        $item = $this->createCareerPathPosition($careerPath, $position);

        $this->actingAs($this->user)
            ->putJson("/api/v1/career-path-positions/{$item->id}", [
                'position_id' => $position->id,
                'sequence' => 2,
            ])
            ->assertOk();
    }

    public function test_update_returns_not_found_for_missing_item(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/v1/career-path-positions/999999', [
                'sequence' => 2,
            ])
            ->assertNotFound();
    }

    public function test_by_career_path_requires_permission(): void
    {
        $careerPath = $this->createCareerPath();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/career-paths/{$careerPath->id}/positions")
            ->assertForbidden();
    }

    public function test_by_career_path_can_list_positions_in_sequence_order(): void
    {
        $careerPath = $this->createCareerPath();
        $positionOne = $this->createPosition(['code' => 'POS-ONE']);
        $positionTwo = $this->createPosition(['code' => 'POS-TWO']);

        $this->createCareerPathPosition(
            $careerPath,
            $positionTwo,
            ['sequence' => 2],
        );
        $this->createCareerPathPosition(
            $careerPath,
            $positionOne,
            ['sequence' => 1],
        );

        $this->actingAs($this->user)
            ->getJson("/api/v1/career-paths/{$careerPath->id}/positions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.position.code', 'POS-ONE')
            ->assertJsonPath('data.1.position.code', 'POS-TWO');
    }

    public function test_by_career_path_returns_not_found_for_missing_career_path(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths/999999/positions')
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $item = $this->createCareerPathPosition();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/career-path-positions/{$item->id}")
            ->assertForbidden();
    }

    public function test_destroy_can_delete_item(): void
    {
        $item = $this->createCareerPathPosition();

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/career-path-positions/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('career_path_positions', [
            'id' => $item->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_item(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/api/v1/career-path-positions/999999')
            ->assertNotFound();
    }

    public function test_resource_returns_expected_fields(): void
    {
        $item = $this->createCareerPathPosition();

        $this->actingAs($this->user)
            ->getJson("/api/v1/career-path-positions/{$item->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'career_path_id',
                    'position_id',
                    'position' => [
                        'id',
                        'code',
                        'name',
                    ],
                    'sequence',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }
}
