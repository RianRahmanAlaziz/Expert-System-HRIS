<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceIndicator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceIndicatorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'performance_indicator.view',
            'performance_indicator.create',
            'performance_indicator.update',
            'performance_indicator.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::create([
            'name' => 'performance-indicator-tester',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permissions);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createIndicator(
        ?string $name = null,
        ?string $description = null,
        ?string $category = null,
        ?float $target = 100,
        float $weight = 20,
        string $measurementType = 'score',
        bool $isActive = true,
    ): PerformanceIndicator {
        return PerformanceIndicator::query()->create([
            'name' => $name ?? fake()->unique()->sentence(3),
            'description' => $description ?? 'Performance indicator for testing.',
            'category' => $category ?? 'Quality',
            'target' => $target,
            'weight' => $weight,
            'measurement_type' => $measurementType,
            'is_active' => $isActive,
        ]);
    }

    public function test_requires_authentication_to_access_performance_indicator(): void
    {
        $response = $this->getJson('/api/v1/performance/indicators');

        $response->assertUnauthorized();
    }

    public function test_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/performance/indicators');

        $response->assertForbidden();
    }

    public function test_requires_create_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_indicator.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-indicator-viewer-create-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Quality Performance',
                'weight' => 20,
                'measurement_type' => 'score',
            ]);

        $response->assertForbidden();
    }

    public function test_requires_update_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_indicator.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-indicator-viewer-update-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $indicator = $this->createIndicator();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/performance/indicators/{$indicator->id}",
                [
                    'name' => 'Updated Indicator',
                ],
            );

        $response->assertForbidden();
    }

    public function test_requires_delete_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_indicator.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-indicator-viewer-delete-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $indicator = $this->createIndicator();

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/performance/indicators/{$indicator->id}");

        $response->assertForbidden();
    }

    public function test_can_get_performance_indicator_list(): void
    {
        $indicator = $this->createIndicator(
            name: 'Quality Performance',
            description: 'Quality evaluation indicator.',
            category: 'Quality',
            target: 100,
            weight: 30,
            measurementType: 'score',
            isActive: true,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Daftar Performance indicator berhasil diambil.',
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'category',
                        'target',
                        'weight',
                        'measurement_type',
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
            ])
            ->assertJsonPath(
                'data.0.id',
                $indicator->id,
            );
    }

    public function test_can_get_performance_indicator_detail(): void
    {
        $indicator = $this->createIndicator(
            name: 'Quality Performance',
            description: 'Quality evaluation indicator.',
            category: 'Quality',
            target: 100,
            weight: 30,
            measurementType: 'score',
            isActive: true,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson("/api/v1/performance/indicators/{$indicator->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance indicator berhasil diambil.',
            )
            ->assertJsonPath(
                'data.id',
                $indicator->id,
            )
            ->assertJsonPath(
                'data.name',
                'Quality Performance',
            )
            ->assertJsonPath(
                'data.description',
                'Quality evaluation indicator.',
            )
            ->assertJsonPath(
                'data.category',
                'Quality',
            )
            ->assertJsonPath(
                'data.target',
                '100.00',
            )
            ->assertJsonPath(
                'data.weight',
                '30.00',
            )
            ->assertJsonPath(
                'data.measurement_type',
                'score',
            )
            ->assertJsonPath(
                'data.is_active',
                true,
            );
    }

    public function test_returns_404_for_unknown_performance_indicator(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators/999999');

        $response->assertNotFound();
    }

    public function test_can_get_active_performance_indicators(): void
    {
        $activeIndicator = $this->createIndicator(
            name: 'Active Indicator',
            isActive: true,
        );

        $this->createIndicator(
            name: 'Inactive Indicator',
            isActive: false,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators/active');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance indicator aktif berhasil diambil.',
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'category',
                        'target',
                        'weight',
                        'measurement_type',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonPath(
                'data.0.id',
                $activeIndicator->id,
            );
    }

    public function test_active_endpoint_does_not_return_inactive_indicators(): void
    {
        $this->createIndicator(
            name: 'Active Indicator',
            isActive: true,
        );

        $this->createIndicator(
            name: 'Inactive Indicator',
            isActive: false,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators/active');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Active Indicator',
            )
            ->assertJsonPath(
                'data.0.is_active',
                true,
            );
    }

    public function test_can_create_performance_indicator(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Quality Performance',
                'description' => 'Quality evaluation indicator.',
                'category' => 'Quality',
                'target' => 100,
                'weight' => 30,
                'measurement_type' => 'score',
                'is_active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Performance indicator berhasil dibuat.',
            )
            ->assertJsonPath(
                'data.name',
                'Quality Performance',
            )
            ->assertJsonPath(
                'data.description',
                'Quality evaluation indicator.',
            )
            ->assertJsonPath(
                'data.category',
                'Quality',
            )
            ->assertJsonPath(
                'data.target',
                '100.00',
            )
            ->assertJsonPath(
                'data.weight',
                '30.00',
            )
            ->assertJsonPath(
                'data.measurement_type',
                'score',
            )
            ->assertJsonPath(
                'data.is_active',
                true,
            );

        $this->assertDatabaseHas('performance_indicators', [
            'name' => 'Quality Performance',
            'category' => 'Quality',
            'weight' => 30,
            'measurement_type' => 'score',
            'is_active' => true,
        ]);
    }

    public function test_uses_default_is_active_when_omitted(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Default Active Indicator',
                'weight' => 20,
                'measurement_type' => 'score',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.is_active',
                true,
            );

        $this->assertDatabaseHas('performance_indicators', [
            'name' => 'Default Active Indicator',
            'is_active' => true,
        ]);
    }

    public function test_rejects_missing_name(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'weight' => 20,
                'measurement_type' => 'score',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_rejects_missing_weight(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Quality Performance',
                'measurement_type' => 'score',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_rejects_missing_measurement_type(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Quality Performance',
                'weight' => 20,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['measurement_type']);
    }

    public function test_rejects_negative_target(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Invalid Target',
                'target' => -1,
                'weight' => 20,
                'measurement_type' => 'score',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target']);
    }

    public function test_rejects_negative_weight(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Invalid Weight',
                'weight' => -1,
                'measurement_type' => 'score',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_rejects_weight_above_100(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Invalid Weight',
                'weight' => 100.01,
                'measurement_type' => 'score',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_rejects_invalid_is_active(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/indicators', [
                'name' => 'Invalid Active Status',
                'weight' => 20,
                'measurement_type' => 'score',
                'is_active' => 'invalid',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_can_update_performance_indicator(): void
    {
        $indicator = $this->createIndicator(
            name: 'Quality Performance',
            category: 'Quality',
            target: 100,
            weight: 20,
            measurementType: 'score',
            isActive: true,
        );

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/indicators/{$indicator->id}",
                [
                    'name' => 'Updated Quality Performance',
                    'description' => 'Updated description.',
                    'category' => 'Productivity',
                    'target' => 120,
                    'weight' => 30,
                    'measurement_type' => 'percentage',
                    'is_active' => false,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance indicator berhasil diperbarui.',
            )
            ->assertJsonPath(
                'data.name',
                'Updated Quality Performance',
            )
            ->assertJsonPath(
                'data.description',
                'Updated description.',
            )
            ->assertJsonPath(
                'data.category',
                'Productivity',
            )
            ->assertJsonPath(
                'data.target',
                '120.00',
            )
            ->assertJsonPath(
                'data.weight',
                '30.00',
            )
            ->assertJsonPath(
                'data.measurement_type',
                'percentage',
            )
            ->assertJsonPath(
                'data.is_active',
                false,
            );

        $this->assertDatabaseHas('performance_indicators', [
            'id' => $indicator->id,
            'name' => 'Updated Quality Performance',
            'weight' => 30,
            'is_active' => false,
        ]);
    }

    public function test_can_patch_performance_indicator(): void
    {
        $indicator = $this->createIndicator(
            name: 'Quality Performance',
            category: 'Quality',
            target: 100,
            weight: 20,
            measurementType: 'score',
            isActive: true,
        );

        $response = $this
            ->actingAs($this->user)
            ->patchJson(
                "/api/v1/performance/indicators/{$indicator->id}",
                [
                    'name' => 'Patched Performance Indicator',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Patched Performance Indicator',
            )
            ->assertJsonPath(
                'data.category',
                'Quality',
            )
            ->assertJsonPath(
                'data.target',
                '100.00',
            )
            ->assertJsonPath(
                'data.weight',
                '20.00',
            )
            ->assertJsonPath(
                'data.measurement_type',
                'score',
            )
            ->assertJsonPath(
                'data.is_active',
                true,
            );
    }

    public function test_rejects_invalid_update_weight(): void
    {
        $indicator = $this->createIndicator();

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/indicators/{$indicator->id}",
                [
                    'weight' => 101,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_rejects_negative_update_target(): void
    {
        $indicator = $this->createIndicator();

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/indicators/{$indicator->id}",
                [
                    'target' => -10,
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['target']);
    }

    public function test_rejects_invalid_update_status(): void
    {
        $indicator = $this->createIndicator();

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/indicators/{$indicator->id}",
                [
                    'is_active' => 'invalid',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_can_search_performance_indicator_by_name(): void
    {
        $this->createIndicator(
            name: 'Customer Satisfaction',
        );

        $this->createIndicator(
            name: 'Employee Productivity',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators?search=Customer');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.name',
                'Customer Satisfaction',
            );
    }

    public function test_can_search_performance_indicator_by_description(): void
    {
        $this->createIndicator(
            name: 'Indicator A',
            description: 'Customer satisfaction measurement.',
        );

        $this->createIndicator(
            name: 'Indicator B',
            description: 'Employee productivity measurement.',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators?search=Customer');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.name',
                'Indicator A',
            );
    }

    public function test_can_filter_performance_indicator_by_category(): void
    {
        $this->createIndicator(
            name: 'Quality Indicator',
            category: 'Quality',
        );

        $this->createIndicator(
            name: 'Productivity Indicator',
            category: 'Productivity',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators?category=Quality');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.name',
                'Quality Indicator',
            );
    }

    public function test_can_filter_active_performance_indicators(): void
    {
        $this->createIndicator(
            name: 'Active Indicator',
            isActive: true,
        );

        $this->createIndicator(
            name: 'Inactive Indicator',
            isActive: false,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators?is_active=true');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.name',
                'Active Indicator',
            )
            ->assertJsonPath(
                'data.0.is_active',
                true,
            );
    }

    public function test_can_filter_inactive_performance_indicators(): void
    {
        $this->createIndicator(
            name: 'Active Indicator',
            isActive: true,
        );

        $this->createIndicator(
            name: 'Inactive Indicator',
            isActive: false,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators?is_active=false');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.name',
                'Inactive Indicator',
            )
            ->assertJsonPath(
                'data.0.is_active',
                false,
            );
    }

    public function test_rejects_invalid_per_page(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators?per_page=101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_can_paginate_performance_indicators(): void
    {
        $this->createIndicator(
            name: 'Indicator A',
        );

        $this->createIndicator(
            name: 'Indicator B',
        );

        $this->createIndicator(
            name: 'Indicator C',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators?per_page=2');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                3,
            )
            ->assertJsonPath(
                'meta.pagination.last_page',
                2,
            );
    }

    public function test_uses_default_pagination(): void
    {
        $this->createIndicator();

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/indicators');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            );
    }

    public function test_can_delete_performance_indicator(): void
    {
        $indicator = $this->createIndicator();

        $response = $this
            ->actingAs($this->user)
            ->deleteJson("/api/v1/performance/indicators/{$indicator->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance indicator berhasil dihapus.',
            )
            ->assertJsonPath(
                'data',
                null,
            );

        $this->assertDatabaseMissing('performance_indicators', [
            'id' => $indicator->id,
        ]);
    }

    public function test_returns_404_when_deleting_unknown_indicator(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->deleteJson('/api/v1/performance/indicators/999999');

        $response->assertNotFound();
    }
}
