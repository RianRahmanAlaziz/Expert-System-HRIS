<?php

namespace Tests\Feature\Dashboard;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReportControllerTest extends TestCase
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

    public function test_employee_report_can_be_retrieved(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->createEmployee(
            $department,
            $position,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/employees');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'employee_number',
                        'first_name',
                        'last_name',
                        'full_name',
                        'department',
                        'position',
                        'employment_type',
                        'employment_status',
                        'join_date',
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
            ]);
    }

    public function test_user_without_employee_report_permission_cannot_retrieve_report(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/reports/employees');

        $response->assertForbidden();
    }

    public function test_employee_report_can_filter_by_search(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->createEmployee(
            $department,
            $position,
            [
                'employee_number' => 'EMP-001',
                'first_name' => 'John',
            ],
        );

        $this->createEmployee(
            $department,
            $position,
            [
                'employee_number' => 'EMP-002',
                'first_name' => 'Jane',
            ],
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/employees?search=John');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.first_name', 'John');
    }

    public function test_employee_report_can_filter_by_department(): void
    {
        $departmentOne = $this->createDepartment();
        $departmentTwo = $this->createDepartment();
        $position = $this->createPosition();

        $this->createEmployee(
            $departmentOne,
            $position,
        );

        $this->createEmployee(
            $departmentTwo,
            $position,
            [
                'employee_number' => 'EMP-002',
            ],
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson("/api/v1/reports/employees?department_id={$departmentTwo->id}");

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath(
                'data.0.department.id',
                $departmentTwo->id,
            );
    }

    public function test_employee_report_can_filter_by_position(): void
    {
        $department = $this->createDepartment();
        $positionOne = $this->createPosition();
        $positionTwo = $this->createPosition();

        $this->createEmployee(
            $department,
            $positionOne,
        );

        $this->createEmployee(
            $department,
            $positionTwo,
            [
                'employee_number' => 'EMP-002',
            ],
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson("/api/v1/reports/employees?position_id={$positionTwo->id}");

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath(
                'data.0.position.id',
                $positionTwo->id,
            );
    }

    public function test_employee_report_can_filter_by_employment_type(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->createEmployee(
            $department,
            $position,
            [
                'employment_type' => 'full_time',
            ],
        );

        $this->createEmployee(
            $department,
            $position,
            [
                'employee_number' => 'EMP-002',
                'employment_type' => 'contract',
            ],
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/employees?employment_type=contract');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath(
                'data.0.employment_type',
                'contract',
            );
    }

    public function test_employee_report_can_filter_by_employment_status(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->createEmployee(
            $department,
            $position,
            [
                'employment_status' => 'active',
            ],
        );

        $this->createEmployee(
            $department,
            $position,
            [
                'employee_number' => 'EMP-002',
                'employment_status' => 'inactive',
            ],
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/employees?employment_status=inactive');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath(
                'data.0.employment_status',
                'inactive',
            );
    }

    public function test_employee_report_validates_per_page(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/employees?per_page=101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'DEPT-' . uniqid(),
            'name' => 'Department ' . uniqid(),
            'description' => 'Test department.',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => 'POS-' . uniqid(),
            'name' => 'Position ' . uniqid(),
            'description' => 'Test position.',
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
}
