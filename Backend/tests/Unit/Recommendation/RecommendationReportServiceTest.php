<?php

namespace Tests\Unit\Recommendation;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\Position;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\Recommendation\RecommendationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecommendationReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            RecommendationReportService::class,
        );
    }

    private function createEmployee(): Employee
    {
        $suffix = uniqid();

        $department = Department::query()->create([
            'code' => 'DEP-' . $suffix,
            'name' => 'Human Resources ' . $suffix,
            'description' => 'Human Resources Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = Position::query()->create([
            'code' => 'POS-' . $suffix,
            'name' => 'HR Staff ' . $suffix,
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-' . $suffix,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => now()->subYears(30),
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => now()->subYears(2),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    private function createRecommendation(
        Employee $employee,
        User $user,
        string $type = 'promotion',
        string $status = 'pending',
        string $priority = 'medium',
    ): Recommendation {
        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => $type,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return Recommendation::query()->create([
            'employee_id' => $employee->id,
            'expert_consultation_id' => $consultation->id,
            'type' => $type,
            'title' => 'Test Recommendation',
            'description' => 'Test recommendation description.',
            'priority' => $priority,
            'status' => $status,
            'recommended_at' => now(),
        ]);
    }

    public function test_it_can_generate_recommendation_report(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            user: $user,
        );

        $result = $this->service->generate([]);

        $this->assertSame(
            1,
            $result['summary']['total_recommendations'],
        );

        $this->assertCount(
            1,
            $result['recommendations'],
        );
    }

    public function test_it_calculates_summary(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            status: 'pending',
        );

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            type: 'training',
            status: 'approved',
        );

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            type: 'career',
            status: 'implemented',
        );

        $result = $this->service->generate([]);

        $this->assertSame(
            3,
            $result['summary']['total_recommendations'],
        );

        $this->assertSame(
            1,
            $result['summary']['pending_recommendations'],
        );

        $this->assertSame(
            1,
            $result['summary']['approved_recommendations'],
        );

        $this->assertSame(
            1,
            $result['summary']['implemented_recommendations'],
        );
    }

    public function test_it_groups_by_type(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            type: 'promotion',
        );

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            type: 'training',
        );

        $result = $this->service->generate([]);

        $types = collect($result['by_type']);

        $this->assertSame(
            1,
            $types
                ->where('type', 'promotion')
                ->first()['total'],
        );

        $this->assertSame(
            1,
            $types
                ->where('type', 'training')
                ->first()['total'],
        );
    }

    public function test_it_groups_by_status(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            status: 'pending',
        );

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            status: 'approved',
        );

        $result = $this->service->generate([]);

        $statuses = collect($result['by_status']);

        $this->assertSame(
            1,
            $statuses
                ->where('status', 'pending')
                ->first()['total'],
        );

        $this->assertSame(
            1,
            $statuses
                ->where('status', 'approved')
                ->first()['total'],
        );
    }

    public function test_it_can_filter_by_employee(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();
        $otherEmployee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            user: $user,
        );

        $this->createRecommendation(
            employee: $otherEmployee,
            user: $user,
        );

        $result = $this->service->generate([
            'employee_id' => $employee->id,
        ]);

        $this->assertSame(
            1,
            $result['summary']['total_recommendations'],
        );
    }

    public function test_it_can_filter_by_type(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            type: 'promotion',
        );

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            type: 'training',
        );

        $result = $this->service->generate([
            'type' => 'promotion',
        ]);

        $this->assertSame(
            1,
            $result['summary']['total_recommendations'],
        );

        $this->assertSame(
            'promotion',
            $result['recommendations'][0]['type'],
        );
    }

    public function test_it_can_filter_by_status(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            status: 'pending',
        );

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            status: 'approved',
        );

        $result = $this->service->generate([
            'status' => 'approved',
        ]);

        $this->assertSame(
            1,
            $result['summary']['total_recommendations'],
        );

        $this->assertSame(
            'approved',
            $result['recommendations'][0]['status'],
        );
    }

    public function test_it_can_filter_by_priority(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            priority: 'high',
        );

        $this->createRecommendation(
            employee: $employee,
            user: $user,
            priority: 'low',
        );

        $result = $this->service->generate([
            'priority' => 'high',
        ]);

        $this->assertSame(
            1,
            $result['summary']['total_recommendations'],
        );

        $this->assertSame(
            'high',
            $result['recommendations'][0]['priority'],
        );
    }

    public function test_it_returns_empty_report_when_no_recommendation_exists(): void
    {
        $result = $this->service->generate([]);

        $this->assertSame(
            0,
            $result['summary']['total_recommendations'],
        );

        $this->assertSame(
            [],
            $result['recommendations'],
        );
    }
}
