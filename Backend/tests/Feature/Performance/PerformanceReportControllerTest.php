<?php

namespace Tests\Feature\Performance;

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

class PerformanceReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create([
            'name' => 'performance_report.view',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo('performance_report.view');

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createDepartment(
        ?string $code = null,
        ?string $name = null,
    ): Department {
        return Department::query()->create([
            'code' => $code
                ?? fake()->unique()->numerify('DEP####'),
            'name' => $name
                ?? 'Test Department',
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
        ?Department $department = null,
    ): Employee {
        $department ??= $this->createDepartment();
        $position = $this->createPosition();

        return Employee::query()->create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => null,
            'employee_number' => fake()->unique()->numerify('EMP####'),
            'first_name' => 'Test',
            'last_name' => 'Employee',
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
    ): PerformancePeriod {
        return PerformancePeriod::query()->create([
            'name' => $name
                ?? 'Performance Period '
                . fake()->unique()->numerify('####'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'open',
            'description' => 'Performance period for testing.',
        ]);
    }

    private function createReview(
        Employee $employee,
        PerformancePeriod $period,
        string $status = 'approved',
        string $overallScore = '85.00',
    ): PerformanceReview {
        return PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $this->user->id,
            'status' => $status,
            'overall_score' => $overallScore,
            'review_date' => '2026-03-15',
            'comments' => 'Test performance review.',
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/performance/reports')
            ->assertUnauthorized();
    }

    public function test_requires_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/performance/reports')
            ->assertForbidden();
    }

    public function test_can_get_performance_report(): void
    {
        $department = $this->createDepartment(
            name: 'Human Resources',
        );

        $employee = $this->createEmployee(
            department: $department,
        );

        $period = $this->createPeriod(
            name: 'Q1 2026',
        );

        $review = $this->createReview(
            employee: $employee,
            period: $period,
            overallScore: '85.00',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/reports');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Performance report berhasil diambil.',
            )
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_reviews',
                        'total_employees',
                        'average_score',
                        'highest_score',
                        'lowest_score',
                    ],
                    'by_department' => [
                        '*' => [
                            'department_id',
                            'department_name',
                            'total_reviews',
                            'total_employees',
                            'average_score',
                        ],
                    ],
                    'by_period' => [
                        '*' => [
                            'period_id',
                            'period_name',
                            'start_date',
                            'end_date',
                            'total_reviews',
                            'total_employees',
                            'average_score',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath(
                'data.summary.total_reviews',
                1,
            )
            ->assertJsonPath(
                'data.summary.total_employees',
                1,
            )
            ->assertJsonPath(
                'data.summary.average_score',
                85,
            )
            ->assertJsonPath(
                'data.summary.highest_score',
                '85.00',
            )
            ->assertJsonPath(
                'data.summary.lowest_score',
                '85.00',
            )
            ->assertJsonPath(
                'data.by_department.0.department_id',
                $department->id,
            )
            ->assertJsonPath(
                'data.by_department.0.department_name',
                'Human Resources',
            )
            ->assertJsonPath(
                'data.by_department.0.total_reviews',
                1,
            )
            ->assertJsonPath(
                'data.by_department.0.total_employees',
                1,
            )
            ->assertJsonPath(
                'data.by_period.0.period_id',
                $period->id,
            )
            ->assertJsonPath(
                'data.by_period.0.period_name',
                'Q1 2026',
            );
    }

    public function test_only_approved_reviews_are_included(): void
    {
        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();
        $employee3 = $this->createEmployee();
        $employee4 = $this->createEmployee();

        $period = $this->createPeriod();

        $approvedReview = $this->createReview(
            employee: $employee1,
            period: $period,
            status: 'approved',
        );

        $this->createReview(
            employee: $employee2,
            period: $period,
            status: 'draft',
        );

        $this->createReview(
            employee: $employee3,
            period: $period,
            status: 'submitted',
        );

        $this->createReview(
            employee: $employee4,
            period: $period,
            status: 'rejected',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/reports');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_reviews',
                1,
            )
            ->assertJsonPath(
                'data.summary.total_employees',
                1,
            );

        $this->assertDatabaseHas(
            'performance_reviews',
            [
                'id' => $approvedReview->id,
                'status' => 'approved',
            ],
        );
    }

    public function test_returns_empty_report_when_no_approved_reviews_exist(): void
    {
        $employee = $this->createEmployee();
        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee,
            period: $period,
            status: 'draft',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/reports');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_reviews',
                0,
            )
            ->assertJsonPath(
                'data.summary.total_employees',
                0,
            )
            ->assertJsonPath(
                'data.summary.average_score',
                null,
            )
            ->assertJsonPath(
                'data.summary.highest_score',
                null,
            )
            ->assertJsonPath(
                'data.summary.lowest_score',
                null,
            )
            ->assertJsonCount(0, 'data.by_department')
            ->assertJsonCount(0, 'data.by_period');
    }

    public function test_can_filter_by_period(): void
    {
        $employee = $this->createEmployee();

        $period1 = $this->createPeriod(
            name: 'Q1 2026',
        );

        $period2 = $this->createPeriod(
            name: 'Q2 2026',
            startDate: '2026-04-01',
            endDate: '2026-06-30',
        );

        $review1 = $this->createReview(
            employee: $employee,
            period: $period1,
        );

        $this->createReview(
            employee: $employee,
            period: $period2,
        );

        $response = $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/reports?period_id={$period1->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_reviews',
                1,
            )
            ->assertJsonPath(
                'data.by_period.0.period_id',
                $period1->id,
            )
            ->assertJsonPath(
                'data.by_period.0.period_name',
                'Q1 2026',
            );
    }

    public function test_can_filter_by_employee(): void
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
                "/api/v1/performance/reports?employee_id={$employee1->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_reviews',
                1,
            )
            ->assertJsonPath(
                'data.summary.total_employees',
                1,
            )
            ->assertJsonPath(
                'data.by_department.0.total_reviews',
                1,
            );

        $this->assertSame(
            $employee1->id,
            $review1->employee_id,
        );
    }

    public function test_can_filter_by_department(): void
    {
        $department1 = $this->createDepartment(
            name: 'Human Resources',
        );

        $department2 = $this->createDepartment(
            name: 'Information Technology',
        );

        $employee1 = $this->createEmployee(
            department: $department1,
        );

        $employee2 = $this->createEmployee(
            department: $department2,
        );

        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee1,
            period: $period,
        );

        $this->createReview(
            employee: $employee2,
            period: $period,
        );

        $response = $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/reports?department_id={$department1->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_reviews',
                1,
            )
            ->assertJsonPath(
                'data.by_department.0.department_id',
                $department1->id,
            )
            ->assertJsonPath(
                'data.by_department.0.department_name',
                'Human Resources',
            );
    }


    public function test_can_combine_filters(): void
    {
        $department1 = $this->createDepartment(
            name: 'Human Resources',
        );

        $department2 = $this->createDepartment(
            name: 'Information Technology',
        );

        $employee1 = $this->createEmployee(
            department: $department1,
        );

        $employee2 = $this->createEmployee(
            department: $department2,
        );

        $period1 = $this->createPeriod(
            name: 'Q1 2026',
        );

        $period2 = $this->createPeriod(
            name: 'Q2 2026',
            startDate: '2026-04-01',
            endDate: '2026-06-30',
        );

        $this->createReview(
            employee: $employee1,
            period: $period1,
        );

        $this->createReview(
            employee: $employee2,
            period: $period2,
        );

        $response = $this->actingAs($this->user)
            ->getJson(
                "/api/v1/performance/reports"
                    . "?period_id={$period1->id}"
                    . "&employee_id={$employee1->id}"
                    . "&department_id={$department1->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_reviews',
                1,
            )
            ->assertJsonPath(
                'data.summary.total_employees',
                1,
            )
            ->assertJsonPath(
                'data.by_department.0.department_id',
                $department1->id,
            )
            ->assertJsonPath(
                'data.by_period.0.period_id',
                $period1->id,
            );
    }

    public function test_calculates_summary_correctly(): void
    {
        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();
        $employee3 = $this->createEmployee();
        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee1,
            period: $period,
            overallScore: '70.00',
        );

        $this->createReview(
            employee: $employee2,
            period: $period,
            overallScore: '80.00',
        );

        $this->createReview(
            employee: $employee3,
            period: $period,
            overallScore: '90.00',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/reports');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_reviews',
                3,
            )
            ->assertJsonPath(
                'data.summary.total_employees',
                3,
            )
            ->assertJsonPath(
                'data.summary.average_score',
                80,
            )
            ->assertJsonPath(
                'data.summary.highest_score',
                '90.00',
            )
            ->assertJsonPath(
                'data.summary.lowest_score',
                '70.00',
            );
    }

    public function test_groups_reviews_by_department(): void
    {
        $department1 = $this->createDepartment(
            name: 'Human Resources',
        );

        $department2 = $this->createDepartment(
            name: 'Information Technology',
        );

        $employee1 = $this->createEmployee(
            department: $department1,
        );

        $employee2 = $this->createEmployee(
            department: $department1,
        );

        $employee3 = $this->createEmployee(
            department: $department2,
        );

        $period = $this->createPeriod();

        $this->createReview(
            employee: $employee1,
            period: $period,
            overallScore: '80.00',
        );

        $this->createReview(
            employee: $employee2,
            period: $period,
            overallScore: '90.00',
        );

        $this->createReview(
            employee: $employee3,
            period: $period,
            overallScore: '70.00',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/reports');

        $response->assertOk();

        $departments = $response->json('data.by_department');

        $this->assertCount(2, $departments);

        $hr = collect($departments)
            ->firstWhere('department_id', $department1->id);

        $it = collect($departments)
            ->firstWhere('department_id', $department2->id);

        $this->assertSame(2, $hr['total_reviews']);
        $this->assertSame(2, $hr['total_employees']);
        $this->assertSame(85, $hr['average_score']);

        $this->assertSame(1, $it['total_reviews']);
        $this->assertSame(1, $it['total_employees']);
        $this->assertSame(70, $it['average_score']);
    }

    public function test_groups_reviews_by_period(): void
    {
        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee();

        $period1 = $this->createPeriod(
            name: 'Q1 2026',
        );

        $period2 = $this->createPeriod(
            name: 'Q2 2026',
            startDate: '2026-04-01',
            endDate: '2026-06-30',
        );

        $this->createReview(
            employee: $employee1,
            period: $period1,
            overallScore: '80.00',
        );

        $this->createReview(
            employee: $employee2,
            period: $period2,
            overallScore: '90.00',
        );

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/performance/reports');

        $response->assertOk();

        $periods = $response->json('data.by_period');

        $this->assertCount(2, $periods);

        $q1 = collect($periods)
            ->firstWhere('period_id', $period1->id);

        $q2 = collect($periods)
            ->firstWhere('period_id', $period2->id);

        $this->assertSame(1, $q1['total_reviews']);
        $this->assertSame(80, $q1['average_score']);

        $this->assertSame(1, $q2['total_reviews']);
        $this->assertSame(90, $q2['average_score']);
    }

    public function test_validates_period_id(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(
                '/api/v1/performance/reports?period_id=999999',
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'period_id',
            ]);
    }

    public function test_validates_employee_id(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(
                '/api/v1/performance/reports?employee_id=999999',
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_id',
            ]);
    }

    public function test_validates_department_id(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(
                '/api/v1/performance/reports?department_id=999999',
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'department_id',
            ]);
    }
}
