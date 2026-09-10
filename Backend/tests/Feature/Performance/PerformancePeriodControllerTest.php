<?php

namespace Tests\Feature\Performance;

use App\Models\PerformancePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformancePeriodControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'performance_period.view',
            'performance_period.create',
            'performance_period.update',
            'performance_period.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::create([
            'name' => 'performance-period-tester',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permissions);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createPeriod(
        ?string $name = null,
        string $startDate = '2026-01-01',
        string $endDate = '2026-03-31',
        string $status = 'draft',
        ?string $description = null,
    ): PerformancePeriod {
        return PerformancePeriod::query()->create([
            'name' => $name ?? fake()->unique()->sentence(3),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'description' => $description ?? 'Performance period for testing.',
        ]);
    }

    public function test_requires_authentication_to_access_performance_period(): void
    {
        $response = $this->getJson('/api/v1/performance/periods');

        $response->assertUnauthorized();
    }

    public function test_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/performance/periods');

        $response->assertForbidden();
    }

    public function test_requires_create_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_period.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-period-viewer',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/performance/periods', [
                'name' => 'Q1 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
            ]);

        $response->assertForbidden();
    }

    public function test_requires_update_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_period.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-period-viewer-update-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $period = $this->createPeriod();

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/performance/periods/{$period->id}",
                [
                    'name' => 'Updated Period',
                ],
            );

        $response->assertForbidden();
    }

    public function test_requires_delete_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_period.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-period-viewer-delete-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $period = $this->createPeriod();

        $response = $this
            ->actingAs($user)
            ->deleteJson("/api/v1/performance/periods/{$period->id}");

        $response->assertForbidden();
    }

    public function test_can_get_performance_period_list(): void
    {
        $period = $this->createPeriod(
            name: 'Q1 2026',
            startDate: '2026-01-01',
            endDate: '2026-03-31',
            status: 'open',
            description: 'First quarter performance period.',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Daftar Performance period berhasil diambil.',
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'start_date',
                        'end_date',
                        'status',
                        'description',
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
                $period->id,
            );
    }

    public function test_can_get_performance_period_detail(): void
    {
        $period = $this->createPeriod(
            name: 'Q1 2026',
            description: 'First quarter performance period.',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson("/api/v1/performance/periods/{$period->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance period berhasil diambil.',
            )
            ->assertJsonPath(
                'data.id',
                $period->id,
            )
            ->assertJsonPath(
                'data.name',
                'Q1 2026',
            )
            ->assertJsonPath(
                'data.start_date',
                '2026-01-01',
            )
            ->assertJsonPath(
                'data.end_date',
                '2026-03-31',
            )
            ->assertJsonPath(
                'data.status',
                'draft',
            )
            ->assertJsonPath(
                'data.description',
                'First quarter performance period.',
            );
    }

    public function test_returns_404_for_unknown_performance_period(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods/999999');

        $response->assertNotFound();
    }

    public function test_can_create_performance_period(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/periods', [
                'name' => 'Q1 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'status' => 'open',
                'description' => 'First quarter performance period.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Performance period berhasil dibuat.',
            )
            ->assertJsonPath(
                'data.name',
                'Q1 2026',
            )
            ->assertJsonPath(
                'data.start_date',
                '2026-01-01',
            )
            ->assertJsonPath(
                'data.end_date',
                '2026-03-31',
            )
            ->assertJsonPath(
                'data.status',
                'open',
            )
            ->assertJsonPath(
                'data.description',
                'First quarter performance period.',
            );

        $period = PerformancePeriod::query()
            ->where('name', 'Q1 2026')
            ->firstOrFail();

        $this->assertSame(
            '2026-01-01',
            $period->start_date->toDateString(),
        );

        $this->assertSame(
            '2026-03-31',
            $period->end_date->toDateString(),
        );

        $this->assertSame('open', $period->status);
    }

    public function test_uses_draft_status_when_status_is_omitted(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/periods', [
                'name' => 'Q1 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'draft',
            );

        $this->assertDatabaseHas('performance_periods', [
            'name' => 'Q1 2026',
            'status' => 'draft',
        ]);
    }

    public function test_rejects_missing_name(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/periods', [
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_rejects_missing_start_date(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/periods', [
                'name' => 'Q1 2026',
                'end_date' => '2026-03-31',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_rejects_missing_end_date(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/periods', [
                'name' => 'Q1 2026',
                'start_date' => '2026-01-01',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_rejects_end_date_before_start_date(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/periods', [
                'name' => 'Invalid Period',
                'start_date' => '2026-03-31',
                'end_date' => '2026-01-01',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_rejects_invalid_status(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/periods', [
                'name' => 'Invalid Status',
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'status' => 'invalid',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_can_update_performance_period(): void
    {
        $period = $this->createPeriod(
            name: 'Q1 2026',
            status: 'draft',
        );

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/periods/{$period->id}",
                [
                    'name' => 'Q1 2026 Updated',
                    'start_date' => '2026-01-15',
                    'end_date' => '2026-04-15',
                    'status' => 'open',
                    'description' => 'Updated performance period.',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance period berhasil diperbarui.',
            )
            ->assertJsonPath(
                'data.name',
                'Q1 2026 Updated',
            )
            ->assertJsonPath(
                'data.start_date',
                '2026-01-15',
            )
            ->assertJsonPath(
                'data.end_date',
                '2026-04-15',
            )
            ->assertJsonPath(
                'data.status',
                'open',
            )
            ->assertJsonPath(
                'data.description',
                'Updated performance period.',
            );

        $this->assertDatabaseHas('performance_periods', [
            'id' => $period->id,
            'name' => 'Q1 2026 Updated',
            'status' => 'open',
        ]);
    }

    public function test_can_patch_performance_period(): void
    {
        $period = $this->createPeriod(
            name: 'Q1 2026',
            status: 'draft',
        );

        $response = $this
            ->actingAs($this->user)
            ->patchJson(
                "/api/v1/performance/periods/{$period->id}",
                [
                    'name' => 'Q1 2026 Patched',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.name',
                'Q1 2026 Patched',
            )
            ->assertJsonPath(
                'data.start_date',
                '2026-01-01',
            )
            ->assertJsonPath(
                'data.end_date',
                '2026-03-31',
            );
    }

    public function test_rejects_invalid_update_status(): void
    {
        $period = $this->createPeriod();

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/periods/{$period->id}",
                [
                    'status' => 'invalid',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_rejects_invalid_update_end_date(): void
    {
        $period = $this->createPeriod(
            startDate: '2026-01-01',
            endDate: '2026-03-31',
        );

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/periods/{$period->id}",
                [
                    'end_date' => '2025-12-31',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_can_search_performance_period_by_name(): void
    {
        $this->createPeriod(
            name: 'Annual Performance 2026',
        );

        $this->createPeriod(
            name: 'Quarterly Review 2026',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods?search=Annual');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.name',
                'Annual Performance 2026',
            );
    }

    public function test_can_search_performance_period_by_description(): void
    {
        $this->createPeriod(
            name: 'Period A',
            description: 'Management evaluation cycle.',
        );

        $this->createPeriod(
            name: 'Period B',
            description: 'Employee development cycle.',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods?search=Management');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.name',
                'Period A',
            );
    }

    public function test_can_filter_performance_period_by_status(): void
    {
        $this->createPeriod(
            name: 'Draft Period',
            status: 'draft',
        );

        $this->createPeriod(
            name: 'Open Period',
            status: 'open',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods?status=open');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.status',
                'open',
            );
    }

    public function test_rejects_invalid_status_filter(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods?status=invalid');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_rejects_invalid_per_page(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods?per_page=101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_can_paginate_performance_periods(): void
    {
        $this->createPeriod(
            name: 'Period A',
        );

        $this->createPeriod(
            name: 'Period B',
        );

        $this->createPeriod(
            name: 'Period C',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods?per_page=2');

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
        $this->createPeriod();

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/periods');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            );
    }

    public function test_can_delete_performance_period(): void
    {
        $period = $this->createPeriod();

        $response = $this
            ->actingAs($this->user)
            ->deleteJson("/api/v1/performance/periods/{$period->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance period berhasil dihapus.',
            )
            ->assertJsonPath(
                'data',
                null,
            );

        $this->assertDatabaseMissing('performance_periods', [
            'id' => $period->id,
        ]);
    }

    public function test_returns_404_when_deleting_unknown_period(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->deleteJson('/api/v1/performance/periods/999999');

        $response->assertNotFound();
    }
}
