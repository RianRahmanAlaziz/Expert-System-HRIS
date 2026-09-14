<?php

namespace Tests\Unit\Dashboard;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Services\Dashboard\EmployeeReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmployeeReportService::class);
    }

    public function test_it_paginates_employees(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->createEmployee($department, $position);
        $this->createEmployee(
            $department,
            $position,
            [
                'employee_number' => 'EMP-002',
                'first_name' => 'Jane',
            ],
        );

        $result = $this->service->paginate();

        $this->assertSame(2, $result->total());
        $this->assertCount(2, $result->items());
    }

    public function test_it_filters_employees_by_search(): void
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

        $result = $this->service->paginate(
            search: 'John',
        );

        $this->assertSame(1, $result->total());
        $this->assertSame(
            'John',
            $result->items()[0]->first_name,
        );
    }

    public function test_it_filters_employees_by_department(): void
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

        $result = $this->service->paginate(
            departmentId: $departmentTwo->id,
        );

        $this->assertSame(1, $result->total());
        $this->assertSame(
            $departmentTwo->id,
            $result->items()[0]->department_id,
        );
    }

    public function test_it_filters_employees_by_position(): void
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

        $result = $this->service->paginate(
            positionId: $positionTwo->id,
        );

        $this->assertSame(1, $result->total());
        $this->assertSame(
            $positionTwo->id,
            $result->items()[0]->position_id,
        );
    }

    public function test_it_filters_employees_by_employment_type(): void
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

        $result = $this->service->paginate(
            employmentType: 'contract',
        );

        $this->assertSame(1, $result->total());
        $this->assertSame(
            'contract',
            $result->items()[0]->employment_type,
        );
    }

    public function test_it_filters_employees_by_employment_status(): void
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

        $result = $this->service->paginate(
            employmentStatus: 'inactive',
        );

        $this->assertSame(1, $result->total());
        $this->assertSame(
            'inactive',
            $result->items()[0]->employment_status,
        );
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
