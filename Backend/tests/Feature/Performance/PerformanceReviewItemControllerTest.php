<?php

namespace Tests\Feature\Performance;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceReviewItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'performance_review.view',
            'performance_review.create',
            'performance_review.update',
            'performance_review.delete',
            'performance_review.submit',
            'performance_review.approve',
            'performance_review.reject',
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

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => fake()->unique()->numerify('POS####'),
            'name' => 'Test Position',
            'description' => 'Position for testing.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        ?User $user = null,
        ?Employee $manager = null,
        ?string $employeeNumber = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): Employee {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        return Employee::query()->create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $manager?->id,
            'employee_number' => $employeeNumber
                ?? fake()->unique()->numerify('EMP####'),
            'first_name' => $firstName ?? 'Test',
            'last_name' => $lastName ?? 'Employee',
            'gender' => 'male',
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee created for testing.',
        ]);
    }

    private function createPeriod(): PerformancePeriod
    {
        return PerformancePeriod::query()->create([
            'name' => 'Performance Period 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'open',
            'description' => 'Performance period for testing.',
        ]);
    }

    private function createReview(
        ?Employee $employee = null,
        ?PerformancePeriod $period = null,
        ?User $reviewer = null,
        string $status = 'draft',
    ): PerformanceReview {
        $employee ??= $this->createEmployee();
        $period ??= $this->createPeriod();
        $reviewer ??= $this->user;

        return PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $reviewer->id,
            'review_type' => 'manager',
            'status' => $status,
            'overall_score' => null,
            'review_date' => null,
            'comments' => 'Review for testing.',
        ]);
    }

    private function createIndicator(
        ?string $name = null,
        bool $isActive = true,
        array $overrides = [],
    ): PerformanceIndicator {
        return PerformanceIndicator::query()->create(array_merge([
            'code' => 'IND-' . fake()->unique()->numberBetween(1000, 9999),
            'name' => $name ?? 'Test Indicator ' . fake()->unique()->numberBetween(1, 9999),
            'description' => 'Performance indicator for testing.',
            'target' => 100,
            'weight' => 100,
            'unit' => 'score',
            'status' => $isActive ? 'active' : 'inactive',
        ], $overrides));
    }

    private function createItem(
        ?PerformanceReview $review = null,
        ?PerformanceIndicator $indicator = null,
        ?float $score = 85,
        ?string $comments = 'Good performance.',
    ): PerformanceReviewItem {
        $review ??= $this->createReview();
        $indicator ??= $this->createIndicator();

        return PerformanceReviewItem::query()->create([
            'performance_review_id' => $review->id,
            'performance_indicator_id' => $indicator->id,
            'score' => $score,
            'comments' => $comments,
        ]);
    }

    private function createUserWithPermissions(
        array $permissions = [],
        ?string $roleName = null,
    ): User {
        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web',
            );
        }

        $role = Role::create([
            'name' => $roleName ?? 'tester-' . fake()->unique()->numerify('####'),
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_requires_authentication_to_access_performance_review_items(): void
    {
        $review = $this->createReview();

        $this->getJson("/api/v1/performance/reviews/{$review->id}/items")
            ->assertUnauthorized();
    }

    public function test_requires_view_permission(): void
    {
        $review = $this->createReview();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/performance/reviews/{$review->id}/items")
            ->assertForbidden();
    }

    public function test_requires_update_permission_for_store(): void
    {
        $review = $this->createReview();

        $user = $this->createUserWithPermissions([
            'performance_review.view',
        ]);

        $indicator = $this->createIndicator();

        $this->actingAs($user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => 80,
                ],
            )
            ->assertForbidden();
    }

    public function test_requires_update_permission_for_update(): void
    {
        $review = $this->createReview();
        $item = $this->createItem(review: $review);

        $user = $this->createUserWithPermissions([
            'performance_review.view',
        ]);

        $this->actingAs($user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}/items/{$item->id}",
                [
                    'score' => 90,
                ],
            )
            ->assertForbidden();
    }

    public function test_requires_delete_permission(): void
    {
        $review = $this->createReview();
        $item = $this->createItem(review: $review);

        $user = $this->createUserWithPermissions([
            'performance_review.view',
            'performance_review.update',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/performance/reviews/{$review->id}/items/{$item->id}")
            ->assertForbidden();
    }

    public function test_can_get_performance_review_items(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator('Quality');
        $indicator2 = $this->createIndicator('Productivity');

        $this->createItem(
            review: $review,
            indicator: $indicator1,
            score: 85,
        );

        $this->createItem(
            review: $review,
            indicator: $indicator2,
            score: 90,
        );

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/performance/reviews/{$review->id}/items");

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'meta.pagination.current_page',
                1,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            )
            ->assertJsonPath(
                'meta.pagination.from',
                1,
            )
            ->assertJsonPath(
                'meta.pagination.to',
                2,
            )
            ->assertJsonPath(
                'data.0.performance_review_id',
                $review->id,
            );
    }

    public function test_can_get_empty_performance_review_items(): void
    {
        $review = $this->createReview();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/performance/reviews/{$review->id}/items");

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath(
                'meta.pagination.total',
                0,
            )
            ->assertJsonPath(
                'meta.pagination.current_page',
                1,
            );
    }

    public function test_returns_404_for_unknown_performance_review(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/performance/reviews/999999/items')
            ->assertNotFound();
    }

    public function test_can_paginate_performance_review_items(): void
    {
        $review = $this->createReview();

        for ($i = 1; $i <= 20; $i++) {
            $this->createItem(
                review: $review,
                indicator: $this->createIndicator("Indicator {$i}"),
                score: 80,
            );
        }

        $response = $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/reviews/{$review->id}/items?per_page=5&page=2"
            );

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath(
                'meta.pagination.current_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.last_page',
                4,
            )
            ->assertJsonPath(
                'meta.pagination.per_page',
                5,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                20,
            )
            ->assertJsonPath(
                'meta.pagination.from',
                6,
            )
            ->assertJsonPath(
                'meta.pagination.to',
                10,
            );
    }

    public function test_rejects_invalid_per_page(): void
    {
        $review = $this->createReview();

        $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/reviews/{$review->id}/items?per_page=0"
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                1,
            );
    }

    public function test_limits_per_page_to_100(): void
    {
        $review = $this->createReview();

        $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/reviews/{$review->id}/items?per_page=150"
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                100,
            );
    }

    public function test_can_create_performance_review_item(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator();

        $response = $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => 85,
                    'comments' => 'Good performance.',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.performance_review_id',
                $review->id,
            )
            ->assertJsonPath(
                'data.performance_indicator_id',
                $indicator->id,
            )
            ->assertJsonPath(
                'data.score',
                '85.00',
            )
            ->assertJsonPath(
                'data.comments',
                'Good performance.',
            );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'performance_review_id' => $review->id,
                'performance_indicator_id' => $indicator->id,
                'score' => 85,
            ],
        );
    }

    public function test_can_create_item_without_score(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator();

        $response = $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'comments' => 'Score will be added later.',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.score',
                null,
            );
    }

    public function test_rejects_missing_performance_indicator_id(): void
    {
        $review = $this->createReview();

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'score' => 80,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'performance_indicator_id',
            ]);
    }

    public function test_rejects_unknown_performance_indicator(): void
    {
        $review = $this->createReview();

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => 999999,
                    'score' => 80,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'performance_indicator_id',
            ]);
    }

    public function test_rejects_score_below_zero(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator();

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => -1,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'score',
            ]);
    }

    public function test_rejects_score_above_100(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator();

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => 101,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'score',
            ]);
    }

    public function test_rejects_non_numeric_score(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator();

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => 'abc',
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'score',
            ]);
    }

    public function test_rejects_non_string_comment(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator();

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => 80,
                    'comments' => 12345,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'comments',
            ]);
    }

    public function test_cannot_create_duplicate_indicator_in_same_review(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator();

        $this->createItem(
            review: $review,
            indicator: $indicator,
        );

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => 90,
                ],
            )
            ->assertUnprocessable();
    }

    public function test_cannot_create_item_with_inactive_indicator(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator(
            isActive: false,
        );

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => 80,
                ],
            )
            ->assertUnprocessable();
    }

    public function test_cannot_create_item_for_approved_review(): void
    {
        $review = $this->createReview(
            status: 'approved',
        );

        $indicator = $this->createIndicator();

        $this->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/items",
                [
                    'performance_indicator_id' => $indicator->id,
                    'score' => 80,
                ],
            )
            ->assertUnprocessable();
    }

    public function test_can_get_performance_review_item_detail(): void
    {
        $review = $this->createReview();
        $item = $this->createItem(review: $review);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/performance/reviews/{$review->id}/items/{$item->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $item->id,
            )
            ->assertJsonPath(
                'data.performance_review_id',
                $review->id,
            )
            ->assertJsonPath(
                'data.performance_indicator_id',
                $item->performance_indicator_id,
            );
    }

    public function test_returns_404_for_unknown_performance_review_item(): void
    {
        $review = $this->createReview();

        $this->actingAs($this->user)
            ->getJson("/api/v1/performance/reviews/{$review->id}/items/999999")
            ->assertNotFound();
    }

    public function test_returns_404_when_item_does_not_belong_to_review(): void
    {
        $review1 = $this->createReview();
        $review2 = $this->createReview();

        $item = $this->createItem(
            review: $review2,
        );

        $this->actingAs($this->user)
            ->getJson("/api/v1/performance/reviews/{$review1->id}/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_can_update_performance_review_item(): void
    {
        $review = $this->createReview();
        $item = $this->createItem(
            review: $review,
            score: 70,
            comments: 'Old comments.',
        );

        $response = $this->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}/items/{$item->id}",
                [
                    'score' => 95,
                    'comments' => 'Updated comments.',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.score',
                '95.00',
            )
            ->assertJsonPath(
                'data.comments',
                'Updated comments.',
            );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
                'score' => 95,
                'comments' => 'Updated comments.',
            ],
        );
    }

    public function test_can_patch_performance_review_item(): void
    {
        $review = $this->createReview();
        $item = $this->createItem(
            review: $review,
            score: 70,
            comments: 'Old comments.',
        );

        $response = $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/performance/reviews/{$review->id}/items/{$item->id}",
                [
                    'score' => 88,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.score',
                '88.00',
            )
            ->assertJsonPath(
                'data.comments',
                'Old comments.',
            );
    }

    public function test_can_update_performance_indicator(): void
    {
        $review = $this->createReview();
        $indicator1 = $this->createIndicator('Old Indicator');
        $indicator2 = $this->createIndicator('New Indicator');

        $item = $this->createItem(
            review: $review,
            indicator: $indicator1,
        );

        $response = $this->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}/items/{$item->id}",
                [
                    'performance_indicator_id' => $indicator2->id,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.performance_indicator_id',
                $indicator2->id,
            );
    }

    public function test_cannot_update_to_duplicate_indicator(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator('Indicator One');
        $indicator2 = $this->createIndicator('Indicator Two');

        $this->createItem(
            review: $review,
            indicator: $indicator1,
        );

        $item2 = $this->createItem(
            review: $review,
            indicator: $indicator2,
        );

        $this->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}/items/{$item2->id}",
                [
                    'performance_indicator_id' => $indicator1->id,
                ],
            )
            ->assertUnprocessable();
    }

    public function test_cannot_update_to_inactive_indicator(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator('Active Indicator');
        $inactiveIndicator = $this->createIndicator(
            'Inactive Indicator',
            false,
        );

        $item = $this->createItem(
            review: $review,
            indicator: $indicator1,
        );

        $this->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}/items/{$item->id}",
                [
                    'performance_indicator_id' => $inactiveIndicator->id,
                ],
            )
            ->assertUnprocessable();
    }

    public function test_cannot_update_approved_review_item(): void
    {
        $review = $this->createReview(
            status: 'approved',
        );

        $item = $this->createItem(
            review: $review,
        );

        $this->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}/items/{$item->id}",
                [
                    'score' => 95,
                ],
            )
            ->assertUnprocessable();
    }

    public function test_rejects_invalid_update_score(): void
    {
        $review = $this->createReview();
        $item = $this->createItem(
            review: $review,
        );

        $this->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}/items/{$item->id}",
                [
                    'score' => 101,
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'score',
            ]);
    }

    public function test_can_delete_performance_review_item(): void
    {
        $review = $this->createReview();
        $item = $this->createItem(
            review: $review,
        );

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/performance/reviews/{$review->id}/items/{$item->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data',
                null,
            );

        $this->assertDatabaseMissing(
            'performance_review_items',
            [
                'id' => $item->id,
            ],
        );
    }

    public function test_cannot_delete_approved_review_item(): void
    {
        $review = $this->createReview(
            status: 'approved',
        );

        $item = $this->createItem(
            review: $review,
        );

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/performance/reviews/{$review->id}/items/{$item->id}")
            ->assertUnprocessable();
    }

    public function test_returns_404_when_deleting_unknown_performance_review_item(): void
    {
        $review = $this->createReview();

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/performance/reviews/{$review->id}/items/999999")
            ->assertNotFound();
    }

    public function test_returns_404_when_deleting_item_from_another_review(): void
    {
        $review1 = $this->createReview();
        $review2 = $this->createReview();

        $item = $this->createItem(
            review: $review2,
        );

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/performance/reviews/{$review1->id}/items/{$item->id}")
            ->assertNotFound();
    }
}
