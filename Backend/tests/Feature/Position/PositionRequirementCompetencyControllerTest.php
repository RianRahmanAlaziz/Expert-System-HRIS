<?php

namespace Tests\Feature\Position;

use App\Models\Competency;
use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Position;
use App\Models\PositionRequirement;
use App\Models\PositionRequirementCompetency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PositionRequirementCompetencyControllerTest extends TestCase
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
        $this->createDepartment();

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

    private function createCompetency(
        ?string $code = null,
        ?string $name = null,
        ?string $category = null,
    ): Competency {
        return Competency::query()->create([
            'code' => $code
                ?? fake()->unique()->numerify('COMP####'),
            'name' => $name ?? 'Leadership',
            'category' => $category ?? 'Behavioral',
            'description' => 'Test competency.',
            'status' => 'active',
        ]);
    }

    private function createCompetencyLevel(
        ?int $level = null,
        ?string $name = null,
    ): CompetencyLevel {
        return CompetencyLevel::query()->create([
            'level' => $level
                ?? fake()->unique()->numberBetween(1, 9999),
            'name' => $name
                ?? 'Level ' . fake()->unique()->numberBetween(1, 9999),
            'description' => 'Test competency level.',
        ]);
    }

    private function createPositionRequirementCompetency(
        ?PositionRequirement $positionRequirement = null,
        ?Competency $competency = null,
        ?CompetencyLevel $competencyLevel = null,
        array $attributes = [],
    ): PositionRequirementCompetency {
        $positionRequirement ??= $this->createPositionRequirement();
        $competency ??= $this->createCompetency();
        $competencyLevel ??= $this->createCompetencyLevel();

        return PositionRequirementCompetency::query()->create([
            'position_requirement_id' => $positionRequirement->id,
            'competency_id' => $competency->id,
            'required_level_id' => $competencyLevel->id,
            'minimum_score' => 70.00,
            'weight' => 20.00,
            'is_required' => true,
            ...$attributes,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_index(): void
    {
        $this->getJson('/api/v1/position-requirement-competencies')
            ->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_access_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirement-competencies')
            ->assertForbidden();
    }

    public function test_user_can_list_position_requirement_competencies(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $item = $this->createPositionRequirementCompetency();

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirement-competencies')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $item->id,
                'position_requirement_id' => $item->position_requirement_id,
                'competency_id' => $item->competency_id,
            ]);
    }

    public function test_index_uses_default_pagination(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        for ($i = 0; $i < 3; $i++) {
            $this->createPositionRequirementCompetency();
        }

        $this->actingAs($user)
            ->getJson('/api/v1/position-requirement-competencies')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 15);
    }

    public function test_index_supports_custom_pagination(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        for ($i = 0; $i < 5; $i++) {
            $this->createPositionRequirementCompetency();
        }

        $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirement-competencies?per_page=2',
            )
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 5);
    }

    public function test_index_clamps_per_page_to_minimum_one(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $this->createPositionRequirementCompetency();

        $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirement-competencies?per_page=0',
            )
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 1);
    }

    public function test_index_clamps_per_page_to_maximum_one_hundred(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $this->createPositionRequirementCompetency();

        $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirement-competencies?per_page=200',
            )
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_index_can_filter_by_position_requirement_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $requirementA = $this->createPositionRequirement();
        $requirementB = $this->createPositionRequirement();

        $itemA = $this->createPositionRequirementCompetency(
            positionRequirement: $requirementA,
        );

        $itemB = $this->createPositionRequirementCompetency(
            positionRequirement: $requirementB,
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirement-competencies?position_requirement_id='
                    . $requirementA->id,
            )
            ->assertOk();

        $response->assertJsonFragment([
            'id' => $itemA->id,
        ]);

        $this->assertSame(
            $itemA->id,
            $response->json('data.0.id'),
        );

        $this->assertNotSame(
            $itemB->id,
            $response->json('data.0.id'),
        );
    }

    public function test_index_can_filter_by_competency_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $competencyA = $this->createCompetency(
            code: 'COMPFILTER01',
        );

        $competencyB = $this->createCompetency(
            code: 'COMPFILTER02',
        );

        $itemA = $this->createPositionRequirementCompetency(
            competency: $competencyA,
        );

        $itemB = $this->createPositionRequirementCompetency(
            competency: $competencyB,
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirement-competencies?competency_id='
                    . $competencyA->id,
            )
            ->assertOk();

        $response->assertJsonFragment([
            'id' => $itemA->id,
        ]);

        $this->assertNotSame(
            $itemB->id,
            $response->json('data.0.id'),
        );
    }

    public function test_index_can_filter_required_items(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $requiredItem = $this->createPositionRequirementCompetency(
            attributes: [
                'is_required' => true,
            ],
        );

        $optionalItem = $this->createPositionRequirementCompetency(
            attributes: [
                'is_required' => false,
            ],
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirement-competencies?is_required=1',
            )
            ->assertOk();

        $response->assertJsonFragment([
            'id' => $requiredItem->id,
        ]);

        $this->assertNotSame(
            $optionalItem->id,
            $response->json('data.0.id'),
        );
    }

    public function test_index_can_filter_optional_items(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $requiredItem = $this->createPositionRequirementCompetency(
            attributes: [
                'is_required' => true,
            ],
        );

        $optionalItem = $this->createPositionRequirementCompetency(
            attributes: [
                'is_required' => false,
            ],
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirement-competencies?is_required=0',
            )
            ->assertOk();

        $response->assertJsonFragment([
            'id' => $optionalItem->id,
        ]);

        $this->assertNotSame(
            $requiredItem->id,
            $response->json('data.0.id'),
        );
    }

    public function test_user_can_show_position_requirement_competency(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $item = $this->createPositionRequirementCompetency();

        $this->actingAs($user)
            ->getJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $item->id,
                'position_requirement_id' => $item->position_requirement_id,
                'competency_id' => $item->competency_id,
            ]);
    }

    public function test_show_returns_not_found_for_invalid_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $this->actingAs($user)
            ->getJson(
                '/api/v1/position-requirement-competencies/999999',
            )
            ->assertNotFound();
    }

    public function test_user_can_get_competencies_by_requirement(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $requirement = $this->createPositionRequirement();

        $first = $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            attributes: [
                'is_required' => true,
            ],
        );

        $second = $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            attributes: [
                'is_required' => false,
            ],
        );

        $this->actingAs($user)
            ->getJson(
                "/api/v1/position-requirements/{$requirement->id}/competencies",
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $first->id,
            ])
            ->assertJsonFragment([
                'id' => $second->id,
            ]);
    }

    public function test_by_requirement_returns_required_items_first(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $requirement = $this->createPositionRequirement();

        $optional = $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            attributes: [
                'is_required' => false,
            ],
        );

        $required = $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            attributes: [
                'is_required' => true,
            ],
        );

        $response = $this->actingAs($user)
            ->getJson(
                "/api/v1/position-requirements/{$requirement->id}/competencies",
            )
            ->assertOk();

        $data = $response->json('data');

        $this->assertSame($required->id, $data[0]['id']);
        $this->assertSame($optional->id, $data[1]['id']);
    }

    public function test_by_requirement_returns_empty_array_when_no_competencies_exist(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->getJson(
                "/api/v1/position-requirements/{$requirement->id}/competencies",
            )
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_by_requirement_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->getJson(
                "/api/v1/position-requirements/{$requirement->id}/competencies",
            )
            ->assertForbidden();
    }

    public function test_user_can_create_position_requirement_competency(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $payload = [
            'position_requirement_id' => $requirement->id,
            'competency_id' => $competency->id,
            'required_level_id' => $level->id,
            'minimum_score' => 75,
            'weight' => 25,
            'is_required' => true,
        ];

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                $payload,
            )
            ->assertCreated()
            ->assertJsonFragment([
                'position_requirement_id' => $requirement->id,
                'competency_id' => $competency->id,
                'required_level_id' => $level->id,
                'is_required' => true,
            ]);

        $this->assertDatabaseHas(
            'position_requirement_competencies',
            [
                'position_requirement_id' => $requirement->id,
                'competency_id' => $competency->id,
            ],
        );
    }

    public function test_store_requires_position_requirement_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'competency_id' => $competency->id,
                    'weight' => 20,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'position_requirement_id',
            ]);
    }

    public function test_store_requires_competency_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'weight' => 20,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'competency_id',
            ]);
    }

    public function test_store_requires_weight(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => $competency->id,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'weight',
            ]);
    }

    public function test_store_rejects_non_existing_position_requirement(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => 999999,
                    'competency_id' => $competency->id,
                    'weight' => 20,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'position_requirement_id',
            ]);
    }

    public function test_store_rejects_non_existing_competency(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => 999999,
                    'weight' => 20,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'competency_id',
            ]);
    }

    public function test_store_rejects_non_existing_required_level(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => $competency->id,
                    'required_level_id' => 999999,
                    'weight' => 20,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'required_level_id',
            ]);
    }

    public function test_store_rejects_minimum_score_above_100(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => $competency->id,
                    'minimum_score' => 101,
                    'weight' => 20,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'minimum_score',
            ]);
    }

    public function test_store_rejects_weight_above_100(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => $competency->id,
                    'weight' => 101,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'weight',
            ]);
    }

    public function test_store_rejects_negative_weight(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => $competency->id,
                    'weight' => -1,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'weight',
            ]);
    }

    public function test_store_rejects_duplicate_competency_for_same_requirement(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            competency: $competency,
        );

        $response = $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => $competency->id,
                    'weight' => 20,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'competency_id',
            ]);
    }

    public function test_user_without_create_permission_cannot_create(): void
    {
        $user = User::factory()->create();

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => $competency->id,
                    'weight' => 20,
                ],
            )
            ->assertForbidden();
    }

    public function test_user_can_update_position_requirement_competency(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.update',
        );

        $item = $this->createPositionRequirementCompetency();

        $this->actingAs($user)
            ->putJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
                [
                    'minimum_score' => 85,
                    'weight' => 30,
                    'is_required' => false,
                ],
            )
            ->assertOk()
            ->assertJsonFragment([
                'minimum_score' => '85.00',
                'weight' => '30.00',
                'is_required' => false,
            ]);
    }

    public function test_user_can_partially_update_position_requirement_competency(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.update',
        );

        $item = $this->createPositionRequirementCompetency(
            attributes: [
                'weight' => 20,
            ],
        );

        $this->actingAs($user)
            ->patchJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
                [
                    'weight' => 40,
                ],
            )
            ->assertOk()
            ->assertJsonFragment([
                'weight' => '40.00',
            ]);

        $this->assertDatabaseHas(
            'position_requirement_competencies',
            [
                'id' => $item->id,
                'weight' => 40,
            ],
        );
    }

    public function test_update_can_change_competency_without_duplicate(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.update',
        );

        $requirement = $this->createPositionRequirement();

        $oldCompetency = $this->createCompetency();
        $newCompetency = $this->createCompetency();

        $item = $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            competency: $oldCompetency,
        );

        $this->actingAs($user)
            ->putJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
                [
                    'competency_id' => $newCompetency->id,
                ],
            )
            ->assertOk()
            ->assertJsonFragment([
                'competency_id' => $newCompetency->id,
            ]);
    }

    public function test_update_rejects_duplicate_competency_for_same_requirement(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.update',
        );

        $requirement = $this->createPositionRequirement();

        $competencyA = $this->createCompetency();
        $competencyB = $this->createCompetency();

        $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            competency: $competencyA,
        );

        $itemB = $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            competency: $competencyB,
        );

        $response = $this->actingAs($user)
            ->putJson(
                "/api/v1/position-requirement-competencies/{$itemB->id}",
                [
                    'competency_id' => $competencyA->id,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'competency_id',
            ]);
    }

    public function test_update_allows_same_competency_on_same_record(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.update',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $item = $this->createPositionRequirementCompetency(
            positionRequirement: $requirement,
            competency: $competency,
        );

        $this->actingAs($user)
            ->putJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
                [
                    'competency_id' => $competency->id,
                    'weight' => 50,
                ],
            )
            ->assertOk()
            ->assertJsonFragment([
                'competency_id' => $competency->id,
                'weight' => '50.00',
            ]);
    }

    public function test_user_without_update_permission_cannot_update(): void
    {
        $user = User::factory()->create();

        $item = $this->createPositionRequirementCompetency();

        $this->actingAs($user)
            ->putJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
                [
                    'weight' => 50,
                ],
            )
            ->assertForbidden();
    }

    public function test_update_returns_not_found_for_invalid_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.update',
        );

        $this->actingAs($user)
            ->putJson(
                '/api/v1/position-requirement-competencies/999999',
                [
                    'weight' => 50,
                ],
            )
            ->assertNotFound();
    }

    public function test_user_can_delete_position_requirement_competency(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.delete',
        );

        $item = $this->createPositionRequirementCompetency();

        $this->actingAs($user)
            ->deleteJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
            )
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing(
            'position_requirement_competencies',
            [
                'id' => $item->id,
            ],
        );
    }

    public function test_user_without_delete_permission_cannot_delete(): void
    {
        $user = User::factory()->create();

        $item = $this->createPositionRequirementCompetency();

        $this->actingAs($user)
            ->deleteJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
            )
            ->assertForbidden();
    }

    public function test_delete_returns_not_found_for_invalid_id(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.delete',
        );

        $this->actingAs($user)
            ->deleteJson(
                '/api/v1/position-requirement-competencies/999999',
            )
            ->assertNotFound();
    }

    public function test_store_returns_created_status(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.create',
        );

        $requirement = $this->createPositionRequirement();
        $competency = $this->createCompetency();

        $this->actingAs($user)
            ->postJson(
                '/api/v1/position-requirement-competencies',
                [
                    'position_requirement_id' => $requirement->id,
                    'competency_id' => $competency->id,
                    'weight' => 20,
                ],
            )
            ->assertCreated();
    }

    public function test_resource_contains_competency_and_required_level(): void
    {
        $user = $this->createUserWithPermission(
            'position_requirement_competency.view',
        );

        $competency = $this->createCompetency(
            code: 'COMPRESOURCE',
            name: 'Communication',
            category: 'Behavioral',
        );

        $level = $this->createCompetencyLevel(
            level: 5,
            name: 'Expert',
        );

        $item = $this->createPositionRequirementCompetency(
            competency: $competency,
            competencyLevel: $level,
        );

        $this->actingAs($user)
            ->getJson(
                "/api/v1/position-requirement-competencies/{$item->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.competency.code',
                'COMPRESOURCE',
            )
            ->assertJsonPath(
                'data.competency.name',
                'Communication',
            )
            ->assertJsonPath(
                'data.competency.category',
                'Behavioral',
            )
            ->assertJsonPath(
                'data.required_level.level',
                5,
            )
            ->assertJsonPath(
                'data.required_level.name',
                'Expert',
            );
    }
}
