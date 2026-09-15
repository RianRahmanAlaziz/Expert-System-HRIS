<?php

namespace Tests\Feature\SystemSupport;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('hr-admin');
    }

    public function test_guest_cannot_view_activity_logs(): void
    {
        $response = $this->getJson('/api/v1/activity-logs');

        $response->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_view_activity_logs(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/activity-logs');

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_view_activity_logs(): void
    {
        ActivityLog::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/activity-logs');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
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
    }

    public function test_activity_logs_are_paginated(): void
    {
        ActivityLog::factory()->count(20)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/activity-logs?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                10,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                20,
            );

        $this->assertCount(
            10,
            $response->json('data'),
        );
    }

    public function test_activity_logs_per_page_is_limited_to_100(): void
    {
        ActivityLog::factory()->count(110)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/activity-logs?per_page=110');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                100,
            );
    }

    public function test_activity_logs_can_be_filtered_by_search(): void
    {
        ActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'action' => 'employee_created',
            'module' => 'employee',
        ]);

        ActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'action' => 'leave_approved',
            'module' => 'leave',
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/activity-logs?search=employee');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.action',
                'employee_created',
            );
    }

    public function test_activity_logs_can_be_filtered_by_user(): void
    {
        $otherUser = User::factory()->create();

        ActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'module' => 'employee',
        ]);

        ActivityLog::factory()->create([
            'user_id' => $otherUser->id,
            'module' => 'employee',
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                "/api/v1/activity-logs?user_id={$this->user->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.user_id',
                $this->user->id,
            );
    }

    public function test_activity_logs_can_be_filtered_by_module(): void
    {
        ActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'module' => 'employee',
        ]);

        ActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'module' => 'leave',
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/activity-logs?module=employee');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.module',
                'employee',
            );
    }

    public function test_activity_logs_can_be_filtered_by_action(): void
    {
        ActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'action' => 'created',
            'module' => 'employee',
        ]);

        ActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'action' => 'updated',
            'module' => 'employee',
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/activity-logs?action=created');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.action',
                'created',
            );
    }

    public function test_user_with_permission_can_view_activity_log_detail(): void
    {
        $activityLog = ActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'action' => 'updated',
            'module' => 'employee',
            'old_values' => [
                'status' => 'active',
            ],
            'new_values' => [
                'status' => 'inactive',
            ],
        ]);

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                "/api/v1/activity-logs/{$activityLog->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id',
                $activityLog->id,
            )
            ->assertJsonPath(
                'data.action',
                'updated',
            )
            ->assertJsonPath(
                'data.module',
                'employee',
            )
            ->assertJsonPath(
                'data.old_values.status',
                'active',
            )
            ->assertJsonPath(
                'data.new_values.status',
                'inactive',
            );
    }

    public function test_activity_log_detail_returns_not_found_for_invalid_id(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/activity-logs/999999');

        $response->assertNotFound();
    }
}
