<?php

namespace Tests\Unit\Dashboard;

use App\Models\Attendance;
use App\Models\Competency;
use App\Models\CompetencyLevel;
use App\Models\ConsultationResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCompetency;
use App\Models\ExpertConsultation;
use App\Models\LeaveRequest;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\PositionRequirement;
use App\Models\PositionRequirementCompetency;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\Dashboard\HrDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private HrDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(HrDashboardService::class);
    }

    public function test_it_returns_hr_dashboard_summary(): void
    {
        $result = $this->service->getSummary();

        $this->assertArrayHasKey('employee', $result);
        $this->assertArrayHasKey('attendance', $result);
        $this->assertArrayHasKey('leave', $result);
        $this->assertArrayHasKey('performance', $result);
        $this->assertArrayHasKey('competency', $result);
        $this->assertArrayHasKey('employee_turnover', $result);
        $this->assertArrayHasKey('expert_system', $result);
        $this->assertArrayHasKey('recommendation', $result);
    }

    public function test_it_counts_active_and_inactive_employees(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->createEmployee(
            $department,
            $position,
            ['employment_status' => 'active'],
        );

        $this->createEmployee(
            $department,
            $position,
            [
                'employee_number' => 'EMP-002',
                'employment_status' => 'inactive',
            ],
        );

        $result = $this->service->getSummary();

        $this->assertSame(2, $result['employee']['total']);
        $this->assertSame(1, $result['employee']['active']);
        $this->assertSame(1, $result['employee']['inactive']);
    }

    public function test_it_counts_todays_attendance(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeOne = $this->createEmployee(
            $department,
            $position,
        );

        $employeeTwo = $this->createEmployee(
            $department,
            $position,
            [
                'employee_number' => 'EMP-002',
            ],
        );

        Attendance::query()->create([
            'employee_id' => $employeeOne->id,
            'attendance_date' => today(),
            'check_in' => now(),
            'status' => 'present',
            'late_minutes' => 0,
            'working_minutes' => 480,
        ]);

        Attendance::query()->create([
            'employee_id' => $employeeTwo->id,
            'attendance_date' => today(),
            'check_in' => now(),
            'status' => 'late',
            'late_minutes' => 15,
            'working_minutes' => 465,
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(2, $result['attendance']['total']);
        $this->assertSame(1, $result['attendance']['present']);
        $this->assertSame(1, $result['attendance']['late']);
    }

    public function test_it_counts_pending_leave_requests(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee($department, $position);

        $leaveType = \App\Models\LeaveType::query()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'description' => 'Annual leave',
            'default_days' => 12,
            'status' => 'active',
        ]);

        LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => today(),
            'end_date' => today(),
            'total_days' => 1,
            'reason' => 'Annual leave',
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(1, $result['leave']['pending']);
    }

    public function test_it_calculates_average_performance_score(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeOne = $this->createEmployee(
            $department,
            $position,
        );

        $employeeTwo = $this->createEmployee(
            $department,
            $position,
            [
                'employee_number' => 'EMP-002',
            ],
        );

        $reviewer = User::factory()->create();

        $performancePeriod = PerformancePeriod::query()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        PerformanceReview::query()->create([
            'employee_id' => $employeeOne->id,
            'performance_period_id' => $performancePeriod->id,
            'reviewer_id' => $reviewer->id,
            'overall_score' => 80,
            'status' => 'completed',
        ]);

        PerformanceReview::query()->create([
            'employee_id' => $employeeTwo->id,
            'performance_period_id' => $performancePeriod->id,
            'reviewer_id' => $reviewer->id,
            'overall_score' => 90,
            'status' => 'completed',
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(
            85.0,
            $result['performance']['average_score'],
        );
    }

    public function test_it_counts_training_and_promotion_recommendations(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee($department, $position);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'training',
            'title' => 'Training Recommendation',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'title' => 'Promotion Recommendation',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(
            1,
            $result['recommendation']['training'],
        );

        $this->assertSame(
            1,
            $result['recommendation']['promotion'],
        );
    }

    public function test_it_counts_missing_required_competency_as_gap(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $positionRequirement = $this->createPositionRequirement(
            $position,
        );

        $this->createPositionRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competency,
            competencyLevel: $level,
        );

        $result = $this->service->getSummary();

        $this->assertSame(
            1,
            $result['competency']['gap_count'],
        );
    }

    public function test_it_counts_competency_score_below_minimum_as_gap(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competency = $this->createCompetency();
        $requiredLevel = $this->createCompetencyLevel(
            level: 3,
            name: 'Advanced',
        );

        $employeeLevel = $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
        );

        $positionRequirement = $this->createPositionRequirement(
            $position,
        );

        $this->createPositionRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competency,
            competencyLevel: $requiredLevel,
            attributes: [
                'minimum_score' => 80,
            ],
        );

        EmployeeCompetency::query()->create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $employeeLevel->id,
            'score' => 70,
            'assessed_at' => today(),
            'assessed_by' => null,
            'notes' => null,
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(
            1,
            $result['competency']['gap_count'],
        );
    }

    public function test_it_does_not_count_competency_score_meeting_minimum_as_gap(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel(
            level: 3,
            name: 'Advanced',
        );

        $positionRequirement = $this->createPositionRequirement(
            $position,
        );

        $this->createPositionRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competency,
            competencyLevel: $level,
            attributes: [
                'minimum_score' => 80,
            ],
        );

        EmployeeCompetency::query()->create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $level->id,
            'score' => 85,
            'assessed_at' => today(),
            'assessed_by' => null,
            'notes' => null,
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(
            0,
            $result['competency']['gap_count'],
        );
    }

    public function test_it_counts_competency_level_below_requirement_as_gap(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competency = $this->createCompetency();

        $requiredLevel = $this->createCompetencyLevel(
            level: 3,
            name: 'Advanced',
        );

        $employeeLevel = $this->createCompetencyLevel(
            level: 2,
            name: 'Intermediate',
        );

        $positionRequirement = $this->createPositionRequirement(
            $position,
        );

        $this->createPositionRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competency,
            competencyLevel: $requiredLevel,
            attributes: [
                'minimum_score' => null,
            ],
        );

        EmployeeCompetency::query()->create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $employeeLevel->id,
            'score' => 85,
            'assessed_at' => today(),
            'assessed_by' => null,
            'notes' => null,
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(
            1,
            $result['competency']['gap_count'],
        );
    }

    public function test_it_does_not_count_competency_level_meeting_requirement_as_gap(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competency = $this->createCompetency();

        $requiredLevel = $this->createCompetencyLevel(
            level: 3,
            name: 'Advanced',
        );

        $positionRequirement = $this->createPositionRequirement(
            $position,
        );

        $this->createPositionRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competency,
            competencyLevel: $requiredLevel,
            attributes: [
                'minimum_score' => null,
            ],
        );

        EmployeeCompetency::query()->create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $requiredLevel->id,
            'score' => 85,
            'assessed_at' => today(),
            'assessed_by' => null,
            'notes' => null,
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(
            0,
            $result['competency']['gap_count'],
        );
    }

    public function test_it_ignores_optional_competency_requirement(): void
    {
        $position = $this->createPosition();


        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $positionRequirement = $this->createPositionRequirement(
            $position,
        );

        $this->createPositionRequirementCompetency(
            positionRequirement: $positionRequirement,
            competency: $competency,
            competencyLevel: $level,
            attributes: [
                'is_required' => false,
            ],
        );

        $result = $this->service->getSummary();

        $this->assertSame(
            0,
            $result['competency']['gap_count'],
        );
    }

    public function test_it_returns_zero_turnover_when_no_reliable_turnover_data_exists(): void
    {
        $result = $this->service->getSummary();

        $this->assertSame(
            0,
            $result['employee_turnover']['count'],
        );

        $this->assertSame(
            0,
            $result['employee_turnover']['rate'],
        );
    }

    public function test_returns_expert_system_statistics(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $user = User::factory()->create();

        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => 'promotion',
            'status' => 'completed',
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);

        ConsultationResult::query()->create([
            'expert_consultation_id' => $consultation->id,
            'recommendation' => 'recommended',
            'score' => 85,
            'confidence' => 90,
            'reason' => 'Memenuhi sebagian besar kriteria.',
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'expert_consultation_id' => $consultation->id,
            'type' => 'promotion',
            'title' => 'Rekomendasi Promosi',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'expert_consultation_id' => $consultation->id,
            'type' => 'training',
            'title' => 'Rekomendasi Training',
            'description' => 'Karyawan membutuhkan training.',
            'priority' => 'medium',
            'status' => 'approved',
            'recommended_at' => now(),
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'expert_consultation_id' => $consultation->id,
            'type' => 'employee_risk',
            'title' => 'Risiko Karyawan',
            'description' => 'Terdapat risiko yang perlu diperhatikan.',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $result = $this->service->getSummary();

        $this->assertSame(1, $result['expert_system']['total_consultations']);

        $this->assertSame(
            1,
            $result['expert_system']['recommendation_distribution']['promotion'],
        );

        $this->assertSame(
            1,
            $result['expert_system']['recommendation_distribution']['training'],
        );

        $this->assertSame(
            1,
            $result['expert_system']['recommendation_distribution']['employee_risk'],
        );

        $this->assertSame(
            1,
            $result['expert_system']['high_risk_employee'],
        );

        $this->assertSame(
            1,
            $result['expert_system']['promotion_candidate'],
        );

        $this->assertSame(
            1,
            $result['expert_system']['training_candidate'],
        );

        $this->assertSame(
            0,
            $result['expert_system']['competency_gap'],
        );

        $this->assertCount(
            1,
            $result['expert_system']['recent_consultations'],
        );

        $this->assertSame(
            $consultation->id,
            $result['expert_system']['recent_consultations']->first()->id,
        );
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'HR-' . uniqid(),
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => 'POS-' . uniqid(),
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        Department $department,
        Position $position,
        array $overrides = [],
    ): Employee {
        return Employee::query()->create(array_merge([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-' . strtoupper(substr(uniqid(), -6)),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ], $overrides));
    }

    private function createCompetency(): Competency
    {
        return Competency::query()->create([
            'code' => 'COMP-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Communication',
            'category' => 'Behavioral',
            'description' => 'Test competency.',
            'status' => 'active',
        ]);
    }

    private function createCompetencyLevel(
        int $level = 3,
        string $name = 'Intermediate',
    ): CompetencyLevel {
        return CompetencyLevel::query()->create([
            'level' => $level,
            'name' => $name,
            'description' => 'Test competency level.',
        ]);
    }

    private function createPositionRequirement(
        Position $position,
    ): PositionRequirement {
        return PositionRequirement::query()->create([
            'position_id' => $position->id,
            'minimum_experience_years' => 1,
            'minimum_performance_score' => 70,
            'minimum_attendance_percentage' => 90,
            'description' => 'Test position requirement.',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPositionRequirementCompetency(
        PositionRequirement $positionRequirement,
        Competency $competency,
        CompetencyLevel $competencyLevel,
        array $attributes = [],
    ): PositionRequirementCompetency {
        return PositionRequirementCompetency::query()->create(array_merge([
            'position_requirement_id' => $positionRequirement->id,
            'competency_id' => $competency->id,
            'required_level_id' => $competencyLevel->id,
            'minimum_score' => 70,
            'weight' => 100,
            'is_required' => true,
        ], $attributes));
    }
}
