<?php

namespace Tests\Unit\ExpertSystem;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\ConsultationResult;
use App\Models\Position;
use App\Models\User;
use App\Services\ExpertSystem\ExpertSystemReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpertSystemReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExpertSystemReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            ExpertSystemReportService::class,
        );
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => fake()->unique()->numerify('DEP####'),
            'name' => 'Test Department',
            'description' => 'Test department.',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => fake()->unique()->numerify('POS####'),
            'name' => 'Test Position',
            'description' => 'Test position.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        ?Department $department = null,
        ?Position $position = null,
        ?string $employeeNumber = null,
    ): Employee {
        $department ??= $this->createDepartment();
        $position ??= $this->createPosition();

        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => $employeeNumber
                ?? fake()->unique()->numerify('EMP####'),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => '2024-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    private function createConsultation(
        Employee $employee,
        User $user,
        string $type = 'promotion',
        string $status = 'completed',
    ): ExpertConsultation {
        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => $type,
            'status' => $status,
            'started_at' => now(),
            'completed_at' => $status === 'completed'
                ? now()
                : null,
        ]);

        ConsultationResult::query()->create([
            'expert_consultation_id' => $consultation->id,
            'recommendation' => $type === 'promotion'
                ? 'Recommended'
                : 'Consider',
            'score' => $type === 'promotion'
                ? 85
                : 70,
            'confidence' => $type === 'promotion'
                ? 90
                : 75,
            'reason' => 'Test consultation result.',
            'input_snapshot' => [
                'employee' => [
                    'id' => $employee->id,
                ],
            ],
            'matched_rules' => [],
            'suggested_actions' => [],
        ]);

        return $consultation;
    }

    public function test_it_can_generate_expert_system_report(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $result = $this->service->generate([]);

        $this->assertSame(
            1,
            $result['summary']['total_consultations'],
        );

        $this->assertCount(
            1,
            $result['consultations'],
        );
    }

    public function test_it_calculates_summary(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'promotion',
        );

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'training',
        );

        $result = $this->service->generate([]);

        $this->assertSame(
            2,
            $result['summary']['total_consultations'],
        );

        $this->assertSame(
            2,
            $result['summary']['completed_consultations'],
        );

        $this->assertSame(
            77.5,
            $result['summary']['average_score'],
        );

        $this->assertSame(
            82.5,
            $result['summary']['average_confidence'],
        );
    }

    public function test_it_groups_by_recommendation(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'promotion',
        );

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'training',
        );

        $result = $this->service->generate([]);

        $recommendations = collect(
            $result['by_recommendation'],
        );

        $this->assertSame(
            1,
            $recommendations
                ->where('recommendation', 'Recommended')
                ->first()['total'],
        );

        $this->assertSame(
            1,
            $recommendations
                ->where('recommendation', 'Consider')
                ->first()['total'],
        );
    }

    public function test_it_groups_by_consultation_type(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'promotion',
        );

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'training',
        );

        $result = $this->service->generate([]);

        $types = collect(
            $result['by_consultation_type'],
        );

        $this->assertSame(
            1,
            $types
                ->where('consultation_type', 'promotion')
                ->first()['total'],
        );

        $this->assertSame(
            1,
            $types
                ->where('consultation_type', 'training')
                ->first()['total'],
        );
    }

    public function test_it_can_filter_by_employee(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();
        $otherEmployee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->createConsultation(
            employee: $otherEmployee,
            user: $user,
        );

        $result = $this->service->generate([
            'employee_id' => $employee->id,
        ]);

        $this->assertSame(
            1,
            $result['summary']['total_consultations'],
        );

        $this->assertSame(
            $employee->id,
            $result['consultations'][0]['employee']['id'],
        );
    }

    public function test_it_can_filter_by_consultation_type(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'promotion',
        );

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'training',
        );

        $result = $this->service->generate([
            'consultation_type' => 'promotion',
        ]);

        $this->assertSame(
            1,
            $result['summary']['total_consultations'],
        );

        $this->assertSame(
            'promotion',
            $result['consultations'][0]['consultation_type'],
        );
    }

    public function test_it_can_filter_by_recommendation(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'promotion',
        );

        $this->createConsultation(
            employee: $employee,
            user: $user,
            type: 'training',
        );

        $result = $this->service->generate([
            'recommendation' => 'Recommended',
        ]);

        $this->assertSame(
            1,
            $result['summary']['total_consultations'],
        );

        $this->assertSame(
            'Recommended',
            $result['consultations'][0]['result']['recommendation'],
        );
    }

    public function test_it_returns_empty_report_when_no_consultation_exists(): void
    {
        $result = $this->service->generate([]);

        $this->assertSame(
            0,
            $result['summary']['total_consultations'],
        );

        $this->assertNull(
            $result['summary']['average_score'],
        );

        $this->assertNull(
            $result['summary']['average_confidence'],
        );

        $this->assertSame(
            [],
            $result['consultations'],
        );
    }
}
