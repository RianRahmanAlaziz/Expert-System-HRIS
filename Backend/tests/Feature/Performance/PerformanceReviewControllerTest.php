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

class PerformanceReviewControllerTest extends TestCase
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

    private function createPeriod(
        ?string $name = null,
        string $startDate = '2026-01-01',
        string $endDate = '2026-03-31',
        string $status = 'open',
        ?string $description = null,
    ): PerformancePeriod {
        return PerformancePeriod::query()->create([
            'name' => $name
                ?? 'Performance Period '
                . fake()->unique()->numerify('####'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'description' => $description
                ?? 'Performance period for testing.',
        ]);
    }

    private function createReview(
        Employee $employee,
        PerformancePeriod $period,
        User $reviewer,
        string $reviewType = 'manager',
        string $status = 'draft',
        ?string $overallScore = null,
        ?string $reviewDate = '2026-02-15',
        ?string $comments = null,
    ): PerformanceReview {
        return PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $reviewer->id,
            'review_type' => $reviewType,
            'status' => $status,
            'overall_score' => $overallScore,
            'review_date' => $reviewDate,
            'comments' => $comments ?? 'Test performance review.',
        ]);
    }

    private function createIndicator(
        ?string $name = null,
        string $weight = '100.00',
        bool $isActive = true,
    ): PerformanceIndicator {
        return PerformanceIndicator::query()->create([
            'name' => $name
                ?? 'Performance Indicator '
                . fake()->unique()->numerify('####'),
            'description' => 'Indicator for testing.',
            'category' => 'Performance',
            'target' => '100.00',
            'weight' => $weight,
            'measurement_type' => 'score',
            'is_active' => $isActive,
        ]);
    }

    private function createReviewItem(
        PerformanceReview $review,
        PerformanceIndicator $indicator,
        ?string $score = '80.00',
        ?string $comment = null,
    ): PerformanceReviewItem {
        return PerformanceReviewItem::query()->create([
            'performance_review_id' => $review->id,
            'performance_indicator_id' => $indicator->id,
            'score' => $score,
            'comment' => $comment ?? 'Test review item.',
        ]);
    }

    private function createUserWithPermissions(
        array $permissions,
        ?string $roleName = null,
    ): User {
        $role = Role::create([
            'name' => $roleName
                ?? 'performance-review-test-'
                . fake()->unique()->numerify('####'),
            'guard_name' => 'web',
        ]);

        $permissionModels = [];

        foreach ($permissions as $permission) {
            $permissionModels[] = Permission::where(
                'name',
                $permission,
            )->firstOrFail();
        }

        $role->givePermissionTo($permissionModels);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_requires_authentication_to_access_performance_review(): void
    {
        $response = $this->getJson(
            '/api/v1/performance/reviews',
        );

        $response->assertUnauthorized();
    }

    public function test_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/performance/reviews');

        $response->assertForbidden();
    }

    public function test_requires_create_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_review.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-review-viewer-create-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/performance/reviews', [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'manager',
            ]);

        $response->assertForbidden();
    }

    public function test_requires_update_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_review.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-review-viewer-update-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $user,
        );

        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}",
                [
                    'comments' => 'Updated review.',
                ],
            );

        $response->assertForbidden();
    }

    public function test_requires_delete_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_review.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-review-viewer-delete-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $user,
        );

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/v1/performance/reviews/{$review->id}",
            );

        $response->assertForbidden();
    }

    public function test_requires_submit_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_review.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-review-viewer-submit-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $user,
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/submit",
            );

        $response->assertForbidden();
    }

    public function test_requires_approve_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_review.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-review-viewer-approve-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $user,
            status: 'submitted',
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/approve",
            );

        $response->assertForbidden();
    }

    public function test_requires_reject_permission(): void
    {
        $permission = Permission::where(
            'name',
            'performance_review.view',
        )->firstOrFail();

        $role = Role::create([
            'name' => 'performance-review-viewer-reject-test',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $user,
            status: 'submitted',
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/reject",
            );

        $response->assertForbidden();
    }

    public function test_can_get_performance_review_list(): void
    {
        $employee = $this->createEmployee(
            firstName: 'John',
            lastName: 'Doe',
        );

        $period = $this->createPeriod(
            name: 'Q1 2026',
        );

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/reviews');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Daftar Performance review berhasil diambil.',
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'employee_id',
                        'performance_period_id',
                        'reviewer_id',
                        'review_type',
                        'status',
                        'overall_score',
                        'review_date',
                        'comments',
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
                $review->id,
            );
    }

    public function test_can_get_performance_review_detail(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/reviews/{$review->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance review berhasil diambil.',
            )
            ->assertJsonPath(
                'data.id',
                $review->id,
            )
            ->assertJsonPath(
                'data.employee_id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.performance_period_id',
                $period->id,
            )
            ->assertJsonPath(
                'data.reviewer_id',
                $this->user->id,
            )
            ->assertJsonPath(
                'data.status',
                'draft',
            );
    }

    public function test_returns_404_for_unknown_performance_review(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/performance/reviews/999999',
            );

        $response->assertNotFound();
    }

    public function test_can_create_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/reviews', [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'manager',
                'review_date' => '2026-02-15',
                'comments' => 'Initial performance review.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Performance review berhasil dibuat.',
            )
            ->assertJsonPath(
                'data.employee_id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.performance_period_id',
                $period->id,
            )
            ->assertJsonPath(
                'data.reviewer_id',
                $this->user->id,
            )
            ->assertJsonPath(
                'data.review_type',
                'manager',
            )
            ->assertJsonPath(
                'data.status',
                'draft',
            );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'reviewer_id' => $this->user->id,
                'review_type' => 'manager',
                'status' => 'draft',
            ],
        );
    }

    public function test_rejects_missing_employee_id(): void
    {
        $period = $this->createPeriod();

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/reviews', [
                'performance_period_id' => $period->id,
                'review_type' => 'manager',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_id',
            ]);
    }

    public function test_rejects_missing_performance_period_id(): void
    {
        $employee = $this->createEmployee();

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/reviews', [
                'employee_id' => $employee->id,
                'review_type' => 'manager',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'performance_period_id',
            ]);
    }

    public function test_rejects_invalid_review_type(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/reviews', [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'review_type' => 'invalid',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'review_type',
            ]);
    }

    public function test_reviewer_id_is_taken_from_authenticated_user(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $otherUser = User::factory()->create();

        $response = $this
            ->actingAs($this->user)
            ->postJson('/api/v1/performance/reviews', [
                'employee_id' => $employee->id,
                'performance_period_id' => $period->id,
                'reviewer_id' => $otherUser->id,
                'review_type' => 'manager',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.reviewer_id',
                $this->user->id,
            );
    }

    public function test_can_update_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}",
                [
                    'review_date' => '2026-02-20',
                    'comments' => 'Updated performance review.',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance review berhasil diperbarui.',
            )
            ->assertJsonPath(
                'data.review_date',
                '2026-02-20',
            )
            ->assertJsonPath(
                'data.comments',
                'Updated performance review.',
            );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $review->id,
                'comments' => 'Updated performance review.',
            ],
        );
    }

    public function test_can_patch_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->patchJson(
                "/api/v1/performance/reviews/{$review->id}",
                [
                    'comments' => 'Patched review.',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.comments',
                'Patched review.',
            )
            ->assertJsonPath(
                'data.status',
                'draft',
            );
    }

    public function test_cannot_update_approved_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'approved',
            overallScore: '85.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->putJson(
                "/api/v1/performance/reviews/{$review->id}",
                [
                    'comments' => 'This should not be updated.',
                ],
            );

        $response->assertUnprocessable();
    }

    public function test_can_calculate_performance_review_score(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $indicator = $this->createIndicator(
            weight: '100.00',
        );

        $this->createReviewItem(
            $review,
            $indicator,
            score: '80.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/calculate",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance score berhasil dihitung.',
            )
            ->assertJsonPath(
                'data.id',
                $review->id,
            )
            ->assertJsonPath(
                'data.overall_score',
                '80.00',
            );
    }

    public function test_cannot_calculate_approved_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'approved',
            overallScore: '80.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/calculate",
            );

        $response->assertUnprocessable();
    }

    public function test_cannot_submit_performance_review_without_items(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/submit",
            );

        $response->assertUnprocessable();
    }

    public function test_cannot_submit_performance_review_when_item_has_no_score(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $indicator = $this->createIndicator();

        $this->createReviewItem(
            $review,
            $indicator,
            score: null,
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson(
                "/api/v1/performance/reviews/{$review->id}/submit",
            );

        $response->assertUnprocessable();
    }

    public function test_can_submit_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $indicator = $this->createIndicator(
            weight: '100.00',
        );

        $this->createReviewItem(
            $review,
            $indicator,
            score: '85.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson("/api/v1/performance/reviews/{$review->id}/submit");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance review berhasil disubmit.',
            )
            ->assertJsonPath(
                'data.status',
                'submitted',
            )
            ->assertJsonPath(
                'data.overall_score',
                '85.00',
            );
    }

    public function test_cannot_submit_non_draft_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'submitted',
            overallScore: '85.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson("/api/v1/performance/reviews/{$review->id}/submit");

        $response->assertUnprocessable();
    }

    public function test_can_approve_submitted_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'submitted',
            overallScore: '85.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson("/api/v1/performance/reviews/{$review->id}/approve");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance review berhasil disetujui.',
            )
            ->assertJsonPath(
                'data.status',
                'approved',
            );
    }

    public function test_cannot_approve_draft_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'draft',
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson("/api/v1/performance/reviews/{$review->id}/approve");

        $response->assertUnprocessable();
    }

    public function test_can_reject_submitted_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'submitted',
            overallScore: '85.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson("/api/v1/performance/reviews/{$review->id}/reject");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance review berhasil ditolak.',
            )
            ->assertJsonPath(
                'data.status',
                'rejected',
            );
    }

    public function test_cannot_reject_draft_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'draft',
        );

        $response = $this
            ->actingAs($this->user)
            ->postJson("/api/v1/performance/reviews/{$review->id}/reject");

        $response->assertUnprocessable();
    }

    public function test_can_search_performance_review_by_employee_number(): void
    {
        $employee = $this->createEmployee(
            employeeNumber: 'EMP-SEARCH-001',
        );

        $otherEmployee = $this->createEmployee(
            employeeNumber: 'EMP-OTHER-001',
        );

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $this->createReview(
            $otherEmployee,
            $period,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/reviews?search=EMP-SEARCH-001');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_performance_review_by_employee_id(): void
    {
        $employee = $this->createEmployee();
        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $this->createReview(
            $otherEmployee,
            $period,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson("/api/v1/performance/reviews?employee_id={$employee->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_performance_review_by_period(): void
    {
        $employee = $this->createEmployee();

        $period = $this->createPeriod(
            name: 'Q1 2026',
        );

        $otherPeriod = $this->createPeriod(
            name: 'Q2 2026',
            startDate: '2026-04-01',
            endDate: '2026-06-30',
        );

        $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $this->createReview(
            $employee,
            $otherPeriod,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson("/api/v1/performance/reviews?performance_period_id={$period->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_performance_review_by_review_type(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $this->user,
            reviewType: 'self',
        );

        $this->createReview(
            $employee,
            $period,
            $this->user,
            reviewType: 'manager',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/reviews?review_type=self');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_performance_review_by_status(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'draft',
        );

        $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'submitted',
            overallScore: '85.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/reviews?status=submitted');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rejects_invalid_per_page(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/reviews?per_page=101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_can_paginate_performance_reviews(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        PerformanceReview::query()
            ->count();

        for ($i = 1; $i <= 16; $i++) {
            $this->createReview(
                $employee,
                $period,
                $this->user,
                comments: "Review {$i}",
            );
        }

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/reviews?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                10,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                16,
            );
    }

    public function test_uses_default_pagination(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        for ($i = 1; $i <= 16; $i++) {
            $this->createReview(
                $employee,
                $period,
                $this->user,
                comments: "Review {$i}",
            );
        }

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/performance/reviews');

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

    public function test_manager_can_only_access_direct_report_review(): void
    {
        $managerUser = $this->createUserWithPermissions([
            'performance_review.view',
        ], 'manager');

        $manager = $this->createEmployee(
            user: $managerUser,
        );

        $employeeUser = User::factory()->create();

        $directReport = $this->createEmployee(
            user: $employeeUser,
            manager: $manager,
        );

        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $directReportReview = $this->createReview(
            $directReport,
            $period,
            $managerUser,
        );

        $this->createReview(
            $otherEmployee,
            $period,
            $managerUser,
        );

        $response = $this
            ->actingAs($managerUser)
            ->getJson('/api/v1/performance/reviews');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $directReportReview->id,
            );
    }

    public function test_employee_can_only_access_own_review(): void
    {
        $employeeUser = $this->createUserWithPermissions([
            'performance_review.view',
        ], 'employee');

        $employee = $this->createEmployee(
            user: $employeeUser,
        );

        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $ownReview = $this->createReview(
            $employee,
            $period,
            $employeeUser,
        );

        $this->createReview(
            $otherEmployee,
            $period,
            $employeeUser,
        );

        $response = $this
            ->actingAs($employeeUser)
            ->getJson('/api/v1/performance/reviews');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownReview->id,
            );
    }

    public function test_employee_cannot_approve_performance_review(): void
    {
        $employeeUser = $this->createUserWithPermissions([
            'performance_review.view',
            'performance_review.approve',
        ], 'employee');

        $employee = $this->createEmployee(
            user: $employeeUser,
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $employeeUser,
            status: 'submitted',
            overallScore: '85.00',
        );

        $response = $this
            ->actingAs($employeeUser)
            ->postJson("/api/v1/performance/reviews/{$review->id}/approve");

        $response->assertForbidden();
    }

    public function test_employee_cannot_reject_performance_review(): void
    {
        $employeeUser = $this->createUserWithPermissions([
            'performance_review.view',
            'performance_review.reject',
        ], 'employee');

        $employee = $this->createEmployee(
            user: $employeeUser,
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $employeeUser,
            status: 'submitted',
            overallScore: '85.00',
        );

        $response = $this
            ->actingAs($employeeUser)
            ->postJson("/api/v1/performance/reviews/{$review->id}/reject");

        $response->assertForbidden();
    }

    public function test_cannot_delete_approved_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
            status: 'approved',
            overallScore: '85.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->deleteJson("/api/v1/performance/reviews/{$review->id}");

        $response->assertUnprocessable();

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $review->id,
                'status' => 'approved',
            ],
        );
    }

    public function test_can_delete_performance_review(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $review = $this->createReview(
            $employee,
            $period,
            $this->user,
        );

        $response = $this
            ->actingAs($this->user)
            ->deleteJson("/api/v1/performance/reviews/{$review->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data',
                null,
            );

        $this->assertDatabaseMissing(
            'performance_reviews',
            [
                'id' => $review->id,
            ],
        );
    }

    public function test_returns_404_when_deleting_unknown_performance_review(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->deleteJson('/api/v1/performance/reviews/999999');

        $response->assertNotFound();
    }
}
