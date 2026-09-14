<?php

namespace Tests\Unit\Competency;

use App\Models\Competency;
use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCompetency;
use App\Models\Position;
use App\Models\PositionRequirement;
use App\Models\PositionRequirementCompetency;
use App\Services\Competency\CompetencyReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetencyReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompetencyReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CompetencyReportService::class);
    }

    public function test_it_generates_competency_report(): void
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

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
        );

        $this->createEmployeeCompetency(
            $employee,
            $competency,
            $level,
            85,
        );

        $result = $this->service->generate();

        $this->assertCount(1, $result);

        $this->assertSame(
            $employee->id,
            $result[0]['employee_id'],
        );

        $this->assertSame(
            $competency->id,
            $result[0]['competency_id'],
        );

        $this->assertSame(
            3,
            $result[0]['current_level'],
        );

        $this->assertSame(
            85,
            (int) $result[0]['current_score'],
        );

        $this->assertFalse(
            $result[0]['has_gap'],
        );
    }

    public function test_it_detects_missing_required_competency_as_gap(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
        );

        $result = $this->service->generate();

        $this->assertTrue(
            $result[0]['has_gap'],
        );

        $this->assertNull(
            $result[0]['current_level'],
        );

        $this->assertNull(
            $result[0]['current_score'],
        );
    }

    public function test_it_detects_score_below_minimum_as_gap(): void
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

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
            [
                'minimum_score' => 80,
            ],
        );

        $this->createEmployeeCompetency(
            $employee,
            $competency,
            $level,
            70,
        );

        $result = $this->service->generate();

        $this->assertTrue(
            $result[0]['has_gap'],
        );
    }

    public function test_it_detects_level_below_requirement_as_gap(): void
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

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $requiredLevel,
            [
                'minimum_score' => null,
            ],
        );

        $this->createEmployeeCompetency(
            $employee,
            $competency,
            $employeeLevel,
            85,
        );

        $result = $this->service->generate();

        $this->assertTrue(
            $result[0]['has_gap'],
        );
    }

    public function test_it_does_not_detect_gap_when_requirements_are_met(): void
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

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
            [
                'minimum_score' => 80,
            ],
        );

        $this->createEmployeeCompetency(
            $employee,
            $competency,
            $level,
            85,
        );

        $result = $this->service->generate();

        $this->assertFalse(
            $result[0]['has_gap'],
        );
    }

    public function test_it_ignores_optional_competency_for_gap_detection(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
            [
                'is_required' => false,
            ],
        );

        $result = $this->service->generate();

        $this->assertCount(1, $result);

        $this->assertFalse(
            $result[0]['is_required'],
        );

        $this->assertFalse(
            $result[0]['has_gap'],
        );
    }

    public function test_it_can_filter_by_employee(): void
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
            'EMP-002',
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
        );

        $result = $this->service->generate([
            'employee_id' => $employeeTwo->id,
        ]);

        $this->assertCount(1, $result);

        $this->assertSame(
            $employeeTwo->id,
            $result[0]['employee_id'],
        );

        $this->assertNotSame(
            $employeeOne->id,
            $result[0]['employee_id'],
        );
    }

    public function test_it_can_filter_by_competency(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competencyOne = $this->createCompetency(
            'COMP-001',
        );

        $competencyTwo = $this->createCompetency(
            'COMP-002',
        );

        $level = $this->createCompetencyLevel();

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competencyOne,
            $level,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competencyTwo,
            $level,
        );

        $result = $this->service->generate([
            'competency_id' => $competencyTwo->id,
        ]);

        $this->assertCount(1, $result);

        $this->assertSame(
            $competencyTwo->id,
            $result[0]['competency_id'],
        );
    }

    public function test_it_can_filter_by_department(): void
    {
        $departmentOne = $this->createDepartment('DEP-001');
        $departmentTwo = $this->createDepartment('DEP-002');

        $position = $this->createPosition();

        $employeeOne = $this->createEmployee(
            $departmentOne,
            $position,
        );

        $employeeTwo = $this->createEmployee(
            $departmentTwo,
            $position,
            'EMP-002',
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $requirement = $this->createPositionRequirement(
            $position,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
        );

        $result = $this->service->generate([
            'department_id' => $departmentOne->id,
        ]);

        $this->assertCount(1, $result);

        $this->assertSame(
            $employeeOne->id,
            $result[0]['employee_id'],
        );

        $this->assertNotSame(
            $employeeTwo->id,
            $result[0]['employee_id'],
        );
    }

    public function test_it_can_filter_by_position(): void
    {
        $department = $this->createDepartment();

        $positionOne = $this->createPosition('POS-001');
        $positionTwo = $this->createPosition('POS-002');

        $employeeOne = $this->createEmployee(
            $department,
            $positionOne,
        );

        $employeeTwo = $this->createEmployee(
            $department,
            $positionTwo,
            'EMP-002',
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $requirement = $this->createPositionRequirement(
            $positionOne,
        );

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
        );

        $result = $this->service->generate([
            'position_id' => $positionOne->id,
        ]);

        $this->assertCount(1, $result);

        $this->assertSame(
            $employeeOne->id,
            $result[0]['employee_id'],
        );

        $this->assertNotSame(
            $employeeTwo->id,
            $result[0]['employee_id'],
        );
    }

    public function test_it_returns_empty_when_no_active_employee_exists(): void
    {
        $result = $this->service->generate();

        $this->assertSame([], $result);
    }

    private function createDepartment(
        string $code = 'DEP-001',
    ): Department {
        return Department::query()->create([
            'code' => $code,
            'name' => 'Human Resources',
            'description' => 'Test department.',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(
        string $code = 'POS-001',
    ): Position {
        return Position::query()->create([
            'code' => $code,
            'name' => 'HR Staff',
            'description' => 'Test position.',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        Department $department,
        Position $position,
        string $employeeNumber = 'EMP-001',
    ): Employee {
        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => $employeeNumber,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    private function createCompetency(
        string $code = 'COMP-001',
    ): Competency {
        return Competency::query()->create([
            'code' => $code,
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

    private function createRequirementCompetency(
        PositionRequirement $requirement,
        Competency $competency,
        CompetencyLevel $level,
        array $attributes = [],
    ): PositionRequirementCompetency {
        return PositionRequirementCompetency::query()->create([
            'position_requirement_id' => $requirement->id,
            'competency_id' => $competency->id,
            'required_level_id' => $level->id,
            'minimum_score' => 70,
            'weight' => 100,
            'is_required' => true,
            ...$attributes,
        ]);
    }

    private function createEmployeeCompetency(
        Employee $employee,
        Competency $competency,
        CompetencyLevel $level,
        int $score,
    ): EmployeeCompetency {
        return EmployeeCompetency::query()->create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $level->id,
            'score' => $score,
            'assessed_at' => today(),
            'assessed_by' => null,
            'notes' => null,
        ]);
    }
}
