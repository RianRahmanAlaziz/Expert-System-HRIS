<?php

namespace Tests\Feature\Competency;

use App\Models\CompetencyLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompetencyLevelControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        foreach (
            [
                'competency-level.view',
                'competency-level.create',
                'competency-level.update',
                'competency-level.delete',
            ] as $permission
        ) {
            Permission::findOrCreate(
                $permission,
                'web',
            );
        }
    }

    private function givePermission(string $permission): void
    {
        $this->user->givePermissionTo($permission);
    }

    private function createCompetencyLevel(
        ?int $level = null,
        string $name = 'Intermediate',
        ?string $description = null,
    ): CompetencyLevel {
        return CompetencyLevel::query()->create([
            'level' => $level
                ?? fake()->unique()->numberBetween(1, 9999),
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/competency-levels');

        $response->assertUnauthorized();
    }

    public function test_requires_view_permission_for_index(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/competency-levels');

        $response->assertForbidden();
    }

    public function test_requires_view_permission_for_show(): void
    {
        $competencyLevel = $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->getJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
        );

        $response->assertForbidden();
    }

    public function test_requires_create_permission(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/competency-levels',
            [],
        );

        $response->assertForbidden();
    }

    public function test_requires_update_permission(): void
    {
        $competencyLevel = $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->putJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
            [],
        );

        $response->assertForbidden();
    }

    public function test_requires_delete_permission(): void
    {
        $competencyLevel = $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->deleteJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
        );

        $response->assertForbidden();
    }

    public function test_can_list_competency_levels(): void
    {
        $this->givePermission('competency-level.view');

        $competencyLevel1 = $this->createCompetencyLevel(
            level: 1,
            name: 'Beginner',
            description: 'Basic competency level.',
        );

        $competencyLevel2 = $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
            description: 'Intermediate competency level.',
        );

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/competency-levels');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                2,
            )
            ->assertJsonFragment([
                'id' => $competencyLevel1->id,
            ])
            ->assertJsonFragment([
                'id' => $competencyLevel2->id,
            ]);
    }

    public function test_uses_default_pagination(): void
    {
        $this->givePermission('competency-level.view');

        for ($i = 1; $i <= 16; $i++) {
            $this->createCompetencyLevel(
                level: $i,
                name: "Level {$i}",
            );
        }

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                16,
            );
    }

    public function test_can_paginate_competency_levels(): void
    {
        $this->givePermission('competency-level.view');

        for ($i = 1; $i <= 5; $i++) {
            $this->createCompetencyLevel(
                level: $i,
                name: "Level {$i}",
            );
        }

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels?per_page=2',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                5,
            )
            ->assertJsonPath(
                'meta.pagination.last_page',
                3,
            );
    }

    public function test_per_page_zero_is_limited_to_one(): void
    {
        $this->givePermission('competency-level.view');

        $this->createCompetencyLevel(
            level: 1,
            name: 'Beginner',
        );

        $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels?per_page=0',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                1,
            );
    }

    public function test_per_page_above_100_is_limited_to_100(): void
    {
        $this->givePermission('competency-level.view');

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels?per_page=101',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                100,
            );
    }

    public function test_can_search_by_level(): void
    {
        $this->givePermission('competency-level.view');

        $competencyLevel = $this->createCompetencyLevel(
            level: 25,
            name: 'Advanced',
        );

        $this->createCompetencyLevel(
            level: 50,
            name: 'Expert',
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels?search=25',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $competencyLevel->id,
            ]);
    }

    public function test_can_search_by_name(): void
    {
        $this->givePermission('competency-level.view');

        $competencyLevel = $this->createCompetencyLevel(
            level: 25,
            name: 'Unique Level Name',
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels?search=Unique%20Level%20Name',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $competencyLevel->id,
            ]);
    }

    public function test_can_search_by_description(): void
    {
        $this->givePermission('competency-level.view');

        $competencyLevel = $this->createCompetencyLevel(
            level: 25,
            description: 'Unique competency level description.',
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels?search=Unique%20competency%20level',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $competencyLevel->id,
            ]);
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        $this->givePermission('competency-level.view');

        $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels?search=NOT-FOUND',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                0,
            )
            ->assertJsonPath(
                'data',
                [],
            );
    }

    public function test_can_show_competency_level(): void
    {
        $this->givePermission('competency-level.view');

        $competencyLevel = $this->createCompetencyLevel(
            level: 3,
            name: 'Advanced',
            description: 'Advanced competency level.',
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $competencyLevel->id,
            )
            ->assertJsonPath(
                'data.level',
                3,
            )
            ->assertJsonPath(
                'data.name',
                'Advanced',
            )
            ->assertJsonPath(
                'data.description',
                'Advanced competency level.',
            );
    }

    public function test_returns_404_for_unknown_competency_level(): void
    {
        $this->givePermission('competency-level.view');

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/competency-levels/999999',
        );

        $response->assertNotFound();
    }

    public function test_can_create_competency_level(): void
    {
        $this->givePermission('competency-level.create');

        $payload = [
            'level' => 5,
            'name' => 'Expert',
            'description' => 'Expert competency level.',
        ];

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/competency-levels',
            $payload,
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.level',
                5,
            )
            ->assertJsonPath(
                'data.name',
                'Expert',
            )
            ->assertJsonPath(
                'data.description',
                'Expert competency level.',
            );

        $this->assertDatabaseHas(
            'competency_levels',
            $payload,
        );
    }

    public function test_store_requires_level(): void
    {
        $this->givePermission('competency-level.create');

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/competency-levels',
            [
                'name' => 'Expert',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    }

    public function test_store_requires_name(): void
    {
        $this->givePermission('competency-level.create');

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/competency-levels',
            [
                'level' => 5,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_integer_level(): void
    {
        $this->givePermission('competency-level.create');

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/competency-levels',
            [
                'level' => 'five',
                'name' => 'Expert',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    }

    public function test_store_rejects_duplicate_level(): void
    {
        $this->givePermission('competency-level.create');

        $this->createCompetencyLevel(
            level: 5,
            name: 'Existing Level',
        );

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/competency-levels',
            [
                'level' => 5,
                'name' => 'Duplicate Level',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    }

    public function test_store_rejects_name_longer_than_100_characters(): void
    {
        $this->givePermission('competency-level.create');

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/competency-levels',
            [
                'level' => 5,
                'name' => str_repeat('a', 101),
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_competency_level(): void
    {
        $this->givePermission('competency-level.update');

        $competencyLevel = $this->createCompetencyLevel(
            level: 3,
            name: 'Intermediate',
        );

        $this->actingAs($this->user);

        $response = $this->putJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
            [
                'level' => 4,
                'name' => 'Advanced',
                'description' => 'Updated description.',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.level',
                4,
            )
            ->assertJsonPath(
                'data.name',
                'Advanced',
            )
            ->assertJsonPath(
                'data.description',
                'Updated description.',
            );
    }

    public function test_can_partially_update_competency_level(): void
    {
        $this->givePermission('competency-level.update');

        $competencyLevel = $this->createCompetencyLevel(
            level: 3,
            name: 'Intermediate',
            description: 'Original description.',
        );

        $this->actingAs($this->user);

        $response = $this->patchJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
            [
                'name' => 'Updated Name',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.level',
                3,
            )
            ->assertJsonPath(
                'data.name',
                'Updated Name',
            )
            ->assertJsonPath(
                'data.description',
                'Original description.',
            );
    }

    public function test_update_rejects_duplicate_level(): void
    {
        $this->givePermission('competency-level.update');

        $this->createCompetencyLevel(
            level: 3,
            name: 'Existing Level',
        );

        $competencyLevel = $this->createCompetencyLevel(
            level: 4,
            name: 'Another Level',
        );

        $this->actingAs($this->user);

        $response = $this->putJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
            [
                'level' => 3,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['level']);
    }

    public function test_update_allows_same_level_on_same_record(): void
    {
        $this->givePermission('competency-level.update');

        $competencyLevel = $this->createCompetencyLevel(
            level: 3,
            name: 'Intermediate',
        );

        $this->actingAs($this->user);

        $response = $this->putJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
            [
                'level' => 3,
            ],
        );

        $response->assertOk();
    }

    public function test_returns_404_when_updating_unknown_competency_level(): void
    {
        $this->givePermission('competency-level.update');

        $this->actingAs($this->user);

        $response = $this->putJson(
            '/api/v1/competency-levels/999999',
            [
                'name' => 'Unknown',
            ],
        );

        $response->assertNotFound();
    }

    public function test_can_delete_competency_level(): void
    {
        $this->givePermission('competency-level.delete');

        $competencyLevel = $this->createCompetencyLevel(
            level: 3,
            name: 'Intermediate',
        );

        $this->actingAs($this->user);

        $response = $this->deleteJson(
            "/api/v1/competency-levels/{$competencyLevel->id}",
        );

        $response
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing(
            'competency_levels',
            [
                'id' => $competencyLevel->id,
            ],
        );
    }

    public function test_returns_404_when_deleting_unknown_competency_level(): void
    {
        $this->givePermission('competency-level.delete');

        $this->actingAs($this->user);

        $response = $this->deleteJson(
            '/api/v1/competency-levels/999999',
        );

        $response->assertNotFound();
    }
}
