<?php

namespace Tests\Feature\Competency;

use App\Models\Competency;
use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\PositionRequirement;
use App\Models\PositionRequirementCompetency;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetencyReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole('hr-admin');
    }

    public function test_hr_admin_can_retrieve_competency_report(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $employee = $this->createEmployee(
            $department,
            $position,
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $requirement = $this->createPositionRequirement($position);

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/competencies');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.employee.id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.0.employee.employee_number',
                'EMP-001',
            )
            ->assertJsonPath(
                'data.0.competency.id',
                $competency->id,
            )
            ->assertJsonPath(
                'data.0.requirement.is_required',
                true,
            )
            ->assertJsonPath(
                'data.0.has_gap',
                true,
            );
    }

    public function test_user_without_permission_cannot_retrieve_competency_report(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/reports/competencies');

        $response->assertForbidden();
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

        $requirement = $this->createPositionRequirement($position);

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/competencies?' .
                    'employee_id=' . $employeeTwo->id,
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee.id',
                $employeeTwo->id,
            );

        $this->assertNotEquals(
            $employeeOne->id,
            $response->json('data.0.employee.id'),
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

        $competencyOne = $this->createCompetency('COMP-001');
        $competencyTwo = $this->createCompetency('COMP-002');

        $level = $this->createCompetencyLevel();

        $requirement = $this->createPositionRequirement($position);

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

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/competencies?' .
                    'competency_id=' . $competencyTwo->id,
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.competency.id',
                $competencyTwo->id,
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

        $requirement = $this->createPositionRequirement($position);

        $this->createRequirementCompetency(
            $requirement,
            $competency,
            $level,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/competencies?' .
                    'department_id=' . $departmentOne->id,
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee.id',
                $employeeOne->id,
            );

        $this->assertNotEquals(
            $employeeTwo->id,
            $response->json('data.0.employee.id'),
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

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/competencies?' .
                    'position_id=' . $positionOne->id,
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee.id',
                $employeeOne->id,
            );

        $this->assertNotEquals(
            $employeeTwo->id,
            $response->json('data.0.employee.id'),
        );
    }

    public function test_it_validates_employee_id(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/competencies?employee_id=999999',
            );

        $response->assertUnprocessable();
    }

    public function test_it_validates_competency_id(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/competencies?competency_id=999999',
            );

        $response->assertUnprocessable();
    }

    public function test_it_validates_department_id(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/competencies?department_id=999999',
            );

        $response->assertUnprocessable();
    }

    public function test_it_validates_position_id(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/competencies?position_id=999999',
            );

        $response->assertUnprocessable();
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
    ): PositionRequirementCompetency {
        return PositionRequirementCompetency::query()->create([
            'position_requirement_id' => $requirement->id,
            'competency_id' => $competency->id,
            'required_level_id' => $level->id,
            'minimum_score' => 70,
            'weight' => 100,
            'is_required' => true,
        ]);
    }
}
