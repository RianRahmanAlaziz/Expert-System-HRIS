<?php

namespace Tests\Feature\Competency;

use App\Models\Competency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Tests\TestCase;

class CompetencyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'competency.view',
            'competency.create',
            'competency.update',
            'competency.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permissions);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createCompetency(
        ?string $code = null,
        ?string $name = null,
        ?string $category = null,
        ?string $description = null,
        string $status = 'active',
    ): Competency {
        return Competency::query()->create([
            'code' => $code
                ?? fake()->unique()->bothify('COMP-###'),
            'name' => $name ?? 'Communication',
            'category' => $category ?? 'Behavioral',
            'description' => $description
                ?? 'Communication competency for testing.',
            'status' => $status,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/competencies')
            ->assertUnauthorized();
    }

    public function test_requires_view_permission_for_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/competencies')
            ->assertForbidden();
    }

    public function test_requires_view_permission_for_show(): void
    {
        $competency = $this->createCompetency();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/competencies/{$competency->id}")
            ->assertForbidden();
    }

    public function test_requires_create_permission(): void
    {
        $role = Role::create([
            'name' => 'competency-create-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo(
            Permission::where(
                'name',
                'competency.view',
            )->firstOrFail(),
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->postJson('/api/v1/competencies', [
                'code' => 'COMP-001',
                'name' => 'Communication',
                'category' => 'Behavioral',
                'description' => 'Communication competency.',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_requires_update_permission(): void
    {
        $competency = $this->createCompetency();

        $role = Role::create([
            'name' => 'competency-update-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo(
            Permission::where(
                'name',
                'competency.view',
            )->firstOrFail(),
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->putJson(
                "/api/v1/competencies/{$competency->id}",
                [
                    'name' => 'Updated Communication',
                ],
            )
            ->assertForbidden();
    }

    public function test_requires_delete_permission(): void
    {
        $competency = $this->createCompetency();

        $role = Role::create([
            'name' => 'competency-delete-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo(
            Permission::where(
                'name',
                'competency.view',
            )->firstOrFail(),
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->deleteJson("/api/v1/competencies/{$competency->id}")
            ->assertForbidden();
    }

    public function test_can_list_competencies(): void
    {
        $competency1 = $this->createCompetency(
            code: 'COMP-001',
            name: 'Communication',
        );

        $competency2 = $this->createCompetency(
            code: 'COMP-002',
            name: 'Leadership',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Daftar competency berhasil diambil.',
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'category',
                        'description',
                        'status',
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
            ])
            ->assertJsonFragment([
                'id' => $competency1->id,
            ])
            ->assertJsonFragment([
                'id' => $competency2->id,
            ]);
    }

    public function test_uses_default_pagination(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->createCompetency(
                code: sprintf('COMP-%03d', $i),
                name: "Competency {$i}",
            );
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.current_page',
                1,
            )
            ->assertJsonPath(
                'meta.pagination.last_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                16,
            )
            ->assertJsonPath(
                'meta.pagination.from',
                1,
            )
            ->assertJsonPath(
                'meta.pagination.to',
                15,
            );
    }

    public function test_can_paginate_competencies(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->createCompetency(
                code: sprintf('COMP-%03d', $i),
                name: "Competency {$i}",
            );
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies?per_page=10&page=2');

        $response
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath(
                'meta.pagination.current_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.last_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.per_page',
                10,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                16,
            )
            ->assertJsonPath(
                'meta.pagination.from',
                11,
            )
            ->assertJsonPath(
                'meta.pagination.to',
                16,
            );
    }

    public function test_per_page_zero_is_limited_to_one(): void
    {
        $this->createCompetency();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies?per_page=0');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                1,
            );
    }

    public function test_per_page_above_100_is_limited_to_100(): void
    {
        $this->createCompetency();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies?per_page=150');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                100,
            );
    }

    public function test_can_search_by_code(): void
    {
        $competency = $this->createCompetency(
            code: 'COMP-COM-001',
            name: 'Communication',
        );

        $this->createCompetency(
            code: 'COMP-LEAD-001',
            name: 'Leadership',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies?search=COM-001');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $competency->id,
            );
    }

    public function test_can_search_by_name(): void
    {
        $competency = $this->createCompetency(
            code: 'COMP-001',
            name: 'Strategic Thinking',
        );

        $this->createCompetency(
            code: 'COMP-002',
            name: 'Leadership',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies?search=Strategic');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $competency->id,
            );
    }

    public function test_can_search_by_category(): void
    {
        $competency = $this->createCompetency(
            code: 'COMP-001',
            category: 'Technical',
        );

        $this->createCompetency(
            code: 'COMP-002',
            category: 'Behavioral',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies?search=Technical');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $competency->id,
            );
    }

    public function test_can_search_by_description(): void
    {
        $competency = $this->createCompetency(
            code: 'COMP-001',
            description: 'Problem solving and analytical ability.',
        );

        $this->createCompetency(
            code: 'COMP-002',
            description: 'Communication ability.',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies?search=analytical');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $competency->id,
            );
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        $this->createCompetency();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/competencies?search=NotFound');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath(
                'meta.pagination.total',
                0,
            );
    }

    public function test_can_show_competency(): void
    {
        $competency = $this->createCompetency(
            code: 'COMP-001',
            name: 'Communication',
            category: 'Behavioral',
            description: 'Communication competency.',
            status: 'active',
        );

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/competencies/{$competency->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Detail competency berhasil diambil.',
            )
            ->assertJsonPath(
                'data.id',
                $competency->id,
            )
            ->assertJsonPath(
                'data.code',
                'COMP-001',
            )
            ->assertJsonPath(
                'data.name',
                'Communication',
            )
            ->assertJsonPath(
                'data.category',
                'Behavioral',
            )
            ->assertJsonPath(
                'data.description',
                'Communication competency.',
            )
            ->assertJsonPath(
                'data.status',
                'active',
            );
    }

    public function test_returns_404_for_unknown_competency(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/competencies/999999')
            ->assertNotFound();
    }

    public function test_can_create_competency(): void
    {
        $payload = [
            'code' => 'COMP-001',
            'name' => 'Communication',
            'category' => 'Behavioral',
            'description' => 'Communication competency.',
            'status' => 'active',
        ];

        $response = $this->actingAs($this->user)
            ->postJson(
                '/api/v1/competencies',
                $payload,
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Competency berhasil dibuat.',
            )
            ->assertJsonPath(
                'data.code',
                'COMP-001',
            )
            ->assertJsonPath(
                'data.name',
                'Communication',
            )
            ->assertJsonPath(
                'data.category',
                'Behavioral',
            )
            ->assertJsonPath(
                'data.description',
                'Communication competency.',
            )
            ->assertJsonPath(
                'data.status',
                'active',
            );

        $this->assertDatabaseHas(
            'competencies',
            [
                'code' => 'COMP-001',
                'name' => 'Communication',
            ],
        );
    }

    public function test_store_requires_code(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/competencies', [
                'name' => 'Communication',
                'category' => 'Behavioral',
                'status' => 'active',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/competencies', [
                'code' => 'COMP-001',
                'category' => 'Behavioral',
                'status' => 'active',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);
    }

    public function test_store_requires_category(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/competencies', [
                'code' => 'COMP-001',
                'name' => 'Communication',
                'status' => 'active',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category',
            ]);
    }

    public function test_store_requires_status(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/competencies', [
                'code' => 'COMP-001',
                'name' => 'Communication',
                'category' => 'Behavioral',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $this->createCompetency(
            code: 'COMP-001',
        );

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/competencies', [
                'code' => 'COMP-001',
                'name' => 'Leadership',
                'category' => 'Behavioral',
                'status' => 'active',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_store_rejects_code_longer_than_50_characters(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/competencies', [
                'code' => str_repeat('A', 51),
                'name' => 'Communication',
                'category' => 'Behavioral',
                'status' => 'active',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_can_update_competency(): void
    {
        $competency = $this->createCompetency(
            code: 'COMP-001',
            name: 'Communication',
        );

        $response = $this->actingAs($this->user)
            ->putJson(
                "/api/v1/competencies/{$competency->id}",
                [
                    'code' => 'COMP-UPDATED',
                    'name' => 'Updated Communication',
                    'category' => 'Technical',
                    'description' => 'Updated description.',
                    'status' => 'inactive',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Competency berhasil diperbarui.',
            )
            ->assertJsonPath(
                'data.code',
                'COMP-UPDATED',
            )
            ->assertJsonPath(
                'data.name',
                'Updated Communication',
            )
            ->assertJsonPath(
                'data.category',
                'Technical',
            )
            ->assertJsonPath(
                'data.description',
                'Updated description.',
            )
            ->assertJsonPath(
                'data.status',
                'inactive',
            );

        $this->assertDatabaseHas(
            'competencies',
            [
                'id' => $competency->id,
                'code' => 'COMP-UPDATED',
                'name' => 'Updated Communication',
                'category' => 'Technical',
                'status' => 'inactive',
            ],
        );
    }

    public function test_can_partially_update_competency(): void
    {
        $competency = $this->createCompetency(
            code: 'COMP-001',
            name: 'Communication',
            category: 'Behavioral',
            description: 'Original description.',
            status: 'active',
        );

        $response = $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/competencies/{$competency->id}",
                [
                    'name' => 'Updated Communication',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $competency->id,
            )
            ->assertJsonPath(
                'data.name',
                'Updated Communication',
            )
            ->assertJsonPath(
                'data.code',
                'COMP-001',
            )
            ->assertJsonPath(
                'data.category',
                'Behavioral',
            )
            ->assertJsonPath(
                'data.status',
                'active',
            );
    }

    public function test_update_rejects_duplicate_code(): void
    {
        $this->createCompetency(
            code: 'COMP-001',
        );

        $competency = $this->createCompetency(
            code: 'COMP-002',
        );

        $response = $this->actingAs($this->user)
            ->putJson(
                "/api/v1/competencies/{$competency->id}",
                [
                    'code' => 'COMP-001',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
            ]);
    }

    public function test_update_allows_same_code(): void
    {
        $competency = $this->createCompetency(
            code: 'COMP-001',
        );

        $response = $this->actingAs($this->user)
            ->putJson(
                "/api/v1/competencies/{$competency->id}",
                [
                    'code' => 'COMP-001',
                    'name' => 'Updated Communication',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.code',
                'COMP-001',
            )
            ->assertJsonPath(
                'data.name',
                'Updated Communication',
            );
    }

    public function test_returns_404_when_updating_unknown_competency(): void
    {
        $this->actingAs($this->user)
            ->putJson(
                '/api/v1/competencies/999999',
                [
                    'name' => 'Updated',
                ],
            )
            ->assertNotFound();
    }

    public function test_can_delete_competency(): void
    {
        $competency = $this->createCompetency();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/competencies/{$competency->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Competency berhasil dihapus.',
            )
            ->assertJsonPath(
                'data',
                null,
            );

        $this->assertDatabaseMissing(
            'competencies',
            [
                'id' => $competency->id,
            ],
        );
    }

    public function test_returns_404_when_deleting_unknown_competency(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/api/v1/competencies/999999')
            ->assertNotFound();
    }
}
