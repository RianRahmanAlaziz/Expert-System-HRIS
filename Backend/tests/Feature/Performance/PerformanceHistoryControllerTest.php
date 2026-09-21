<?php

namespace App\Services\Performance;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceHistoryControllerTest extends TestCase
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
    ): PerformancePeriod {
        return PerformancePeriod::query()->create([
            'name' => $name
                ?? 'Performance Period '
                . fake()->unique()->numerify('####'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'description' => 'Performance period for testing.',
        ]);
    }

    private function createReview(
        ?Employee $employee = null,
        ?PerformancePeriod $period = null,
        ?User $reviewer = null,
        string $status = 'approved',
        ?string $reviewedAt = '2026-02-15 00:00:00',
        ?string $overallScore = '85.00',
        ?string $comments = null,
    ): PerformanceReview {
        $employee ??= $this->createEmployee();
        $period ??= $this->createPeriod();
        $reviewer ??= $this->user;

        return PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $reviewer->id,
            'status' => $status,
            'overall_score' => $overallScore,
            'reviewed_at' => $reviewedAt,
            'comments' => $comments ?? 'Test performance review.',
        ]);
    }

    private function createUserWithPermissions(
        array $permissions,
        string $roleName,
    ): User {
        $role = Role::create([
            'name' => $roleName,
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

    public function test_requires_authentication_to_access_performance_history(): void
    {
        $this->getJson('/api/v1/performance/history')
            ->assertUnauthorized();
    }

    public function test_requires_view_permission_for_history(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/performance/history')
            ->assertForbidden();
    }

    public function test_requires_view_permission_for_employee_history(): void
    {
        $employee = $this->createEmployee();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(
                "/api/v1/performance/history/employees/{$employee->id}",
            )
            ->assertForbidden();
    }

    public function test_can_get_approved_performance_history(): void
    {
        $employee = $this->createEmployee(
            firstName: 'John',
            lastName: 'Doe',
            employeeNumber: 'EMP-HISTORY-001',
        );

        $period = $this->createPeriod(
            name: 'Q1 2026',
        );

        $review = $this->createReview(
            employee: $employee,
            period: $period,
            status: 'approved',
            overallScore: '85.00',
            reviewedAt: '2026-03-15 00:00:00',
            comments: 'Excellent performance.',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/history');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance history berhasil diambil.',
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'employee' => [
                            'id',
                            'employee_number',
                            'first_name',
                            'last_name',
                        ],
                        'period' => [
                            'id',
                            'name',
                            'start_date',
                            'end_date',
                            'status',
                        ],
                        'reviewer' => [
                            'id',
                        ],
                        'status',
                        'overall_score',
                        'reviewed_at',
                        'comments',
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
            )
            ->assertJsonPath(
                'data.0.employee.id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.0.employee.employee_number',
                'EMP-HISTORY-001',
            )
            ->assertJsonPath(
                'data.0.employee.first_name',
                'John',
            )
            ->assertJsonPath(
                'data.0.employee.last_name',
                'Doe',
            )
            ->assertJsonPath(
                'data.0.period.id',
                $period->id,
            )
            ->assertJsonPath(
                'data.0.period.name',
                'Q1 2026',
            )
            ->assertJsonPath(
                'data.0.reviewer.id',
                $this->user->id,
            )
            ->assertJsonPath(
                'data.0.status',
                'approved',
            )
            ->assertJsonPath(
                'data.0.overall_score',
                '85.00',
            )
            ->assertJsonPath(
                'data.0.reviewed_at',
                '2026-03-14T17:00:00.000000Z',
            )
            ->assertJsonPath(
                'data.0.comments',
                'Excellent performance.',
            );
    }

    public function test_only_approved_reviews_are_in_history(): void
    {
        $period = $this->createPeriod();

        $approvedEmployee = $this->createEmployee();
        $draftEmployee = $this->createEmployee();
        $submittedEmployee = $this->createEmployee();
        $rejectedEmployee = $this->createEmployee();

        $approvedReview = $this->createReview(
            employee: $approvedEmployee,
            period: $period,
            status: 'approved',
        );

        $this->createReview(
            employee: $draftEmployee,
            period: $period,
            status: 'draft',
        );

        $this->createReview(
            employee: $submittedEmployee,
            period: $period,
            status: 'submitted',
        );

        $this->createReview(
            employee: $rejectedEmployee,
            period: $period,
            status: 'rejected',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/history');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $approvedReview->id,
            );
    }

    public function test_returns_empty_history_when_no_approved_review_exists(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee,
            period: $period,
            status: 'draft',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/history');

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
            )
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            );
    }

    public function test_can_paginate_performance_history(): void
    {
        $period = $this->createPeriod();

        for ($i = 1; $i <= 16; $i++) {
            $employee = $this->createEmployee();

            $this->createReview(
                employee: $employee,
                period: $period,
                reviewedAt: sprintf(
                    '2026-03-%02d 00:00:00',
                    $i,
                ),
                comments: "History {$i}",
            );
        }

        $response = $this->actingAs($this->user)
            ->getJson(
                '/api/v1/performance/history?per_page=10&page=2',
            );

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

    public function test_uses_default_pagination_for_history(): void
    {
        $period = $this->createPeriod();

        for ($i = 1; $i <= 16; $i++) {
            $employee = $this->createEmployee();

            $this->createReview(
                employee: $employee,
                period: $period,
                reviewedAt: sprintf(
                    '2026-03-%02d 00:00:00',
                    $i,
                ),
            );
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/history');

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

    public function test_per_page_zero_is_limited_to_one(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee,
            period: $period,
        );

        $response = $this->actingAs($this->user)
            ->getJson(
                '/api/v1/performance/history?per_page=0',
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
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee,
            period: $period,
        );

        $response = $this->actingAs($this->user)
            ->getJson(
                '/api/v1/performance/history?per_page=150',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                100,
            );
    }

    public function test_history_is_sorted_by_review_date_descending(): void
    {
        $employee = $this->createEmployee();

        $olderPeriod = $this->createPeriod(
            name: 'Q1 2026',
        );

        $newerPeriod = $this->createPeriod(
            name: 'Q2 2026',
        );

        $olderReview = $this->createReview(
            employee: $employee,
            period: $olderPeriod,
            reviewedAt: '2026-01-15 00:00:00',
        );

        $newerReview = $this->createReview(
            employee: $employee,
            period: $newerPeriod,
            reviewedAt: '2026-03-15 00:00:00',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/history');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $newerReview->id,
            )
            ->assertJsonPath(
                'data.1.id',
                $olderReview->id,
            );
    }

    public function test_can_get_history_for_specific_employee(): void
    {
        $employee = $this->createEmployee(
            employeeNumber: 'EMP-HISTORY-001',
        );

        $otherEmployee = $this->createEmployee(
            employeeNumber: 'EMP-HISTORY-002',
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            employee: $employee,
            period: $period,
        );

        $this->createReview(
            employee: $otherEmployee,
            period: $period,
        );

        $response = $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/history/employees/{$employee->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance history employee berhasil diambil.',
            )
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $review->id,
            )
            ->assertJsonPath(
                'data.0.employee.id',
                $employee->id,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            );
    }

    public function test_can_paginate_history_for_specific_employee(): void
    {
        $employee = $this->createEmployee();

        for ($i = 1; $i <= 16; $i++) {
            $period = $this->createPeriod(
                name: "Performance Period {$i}",
            );

            $this->createReview(
                employee: $employee,
                period: $period,
                reviewedAt: sprintf(
                    '2026-03-%02d 00:00:00',
                    $i,
                ),
            );
        }

        $response = $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/history/employees/{$employee->id}?per_page=10&page=2",
            );

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
            );
    }

    public function test_returns_404_for_unknown_employee_history(): void
    {
        $this->actingAs($this->user)
            ->getJson(
                '/api/v1/performance/history/employees/999999',
            )
            ->assertNotFound();
    }

    public function test_admin_can_access_all_employee_history(): void
    {
        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();

        $period = $this->createPeriod();

        $review1 = $this->createReview(
            employee: $employee1,
            period: $period,
        );

        $review2 = $this->createReview(
            employee: $employee2,
            period: $period,
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/history');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $response->assertJsonFragment([
            'id' => $review1->id,
        ]);

        $response->assertJsonFragment([
            'id' => $review2->id,
        ]);
    }

    public function test_admin_can_filter_history_by_employee(): void
    {
        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();

        $period = $this->createPeriod();

        $review1 = $this->createReview(
            employee: $employee1,
            period: $period,
        );

        $this->createReview(
            employee: $employee2,
            period: $period,
        );

        $response = $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/history/employees/{$employee1->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $review1->id,
            );
    }

    public function test_manager_can_only_access_direct_report_history(): void
    {
        $managerUser = $this->createUserWithPermissions(
            ['performance_review.view'],
            'manager',
        );

        $manager = $this->createEmployee(
            user: $managerUser,
        );

        $directReportUser = User::factory()->create();

        $directReport = $this->createEmployee(
            user: $directReportUser,
            manager: $manager,
        );

        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $directReportReview = $this->createReview(
            employee: $directReport,
            period: $period,
            reviewer: $managerUser,
        );

        $otherReview = $this->createReview(
            employee: $otherEmployee,
            period: $period,
            reviewer: $managerUser,
        );

        $response = $this->actingAs($managerUser)
            ->getJson('/api/v1/performance/history');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $directReportReview->id,
            );

        $this->assertNotSame(
            $otherReview->id,
            $response->json('data.0.id'),
        );
    }

    public function test_manager_can_get_direct_report_employee_history(): void
    {
        $managerUser = $this->createUserWithPermissions(
            ['performance_review.view'],
            'manager',
        );

        $manager = $this->createEmployee(
            user: $managerUser,
        );

        $directReportUser = User::factory()->create();

        $directReport = $this->createEmployee(
            user: $directReportUser,
            manager: $manager,
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            employee: $directReport,
            period: $period,
            reviewer: $managerUser,
        );

        $response = $this->actingAs($managerUser)
            ->getJson(
                "/api/v1/performance/history/employees/{$directReport->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $review->id,
            );
    }

    public function test_manager_cannot_get_non_direct_report_employee_history(): void
    {
        $managerUser = $this->createUserWithPermissions(
            ['performance_review.view'],
            'manager',
        );

        $manager = $this->createEmployee(
            user: $managerUser,
        );

        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview(
            employee: $otherEmployee,
            period: $period,
            reviewer: $managerUser,
        );

        $response = $this->actingAs($managerUser)
            ->getJson(
                "/api/v1/performance/history/employees/{$otherEmployee->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath(
                'meta.pagination.total',
                0,
            );
    }

    public function test_employee_can_only_access_own_history(): void
    {
        $employeeUser = $this->createUserWithPermissions(
            ['performance_review.view'],
            'employee',
        );

        $employee = $this->createEmployee(
            user: $employeeUser,
        );

        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $ownReview = $this->createReview(
            employee: $employee,
            period: $period,
            reviewer: $employeeUser,
        );

        $this->createReview(
            employee: $otherEmployee,
            period: $period,
            reviewer: $employeeUser,
        );

        $response = $this->actingAs($employeeUser)
            ->getJson('/api/v1/performance/history');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownReview->id,
            );
    }

    public function test_employee_can_get_own_employee_history(): void
    {
        $employeeUser = $this->createUserWithPermissions(
            ['performance_review.view'],
            'employee',
        );

        $employee = $this->createEmployee(
            user: $employeeUser,
        );

        $period = $this->createPeriod();

        $review = $this->createReview(
            employee: $employee,
            period: $period,
            reviewer: $employeeUser,
        );

        $response = $this->actingAs($employeeUser)
            ->getJson(
                "/api/v1/performance/history/employees/{$employee->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $review->id,
            );
    }

    public function test_employee_cannot_get_other_employee_history(): void
    {
        $employeeUser = $this->createUserWithPermissions(
            ['performance_review.view'],
            'employee',
        );

        $employee = $this->createEmployee(
            user: $employeeUser,
        );

        $otherEmployee = $this->createEmployee();

        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee,
            period: $period,
            reviewer: $employeeUser,
        );

        $this->createReview(
            employee: $otherEmployee,
            period: $period,
            reviewer: $employeeUser,
        );

        $this->actingAs($employeeUser)
            ->getJson(
                "/api/v1/performance/history/employees/{$otherEmployee->id}",
            )
            ->assertForbidden();
    }

    public function test_user_without_supported_role_gets_empty_history(): void
    {
        $user = $this->createUserWithPermissions(
            ['performance_review.view'],
            'performance-history-viewer',
        );

        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee,
            period: $period,
        );

        $response = $this->actingAs($user)
            ->getJson('/api/v1/performance/history');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath(
                'meta.pagination.total',
                0,
            );
    }
}
