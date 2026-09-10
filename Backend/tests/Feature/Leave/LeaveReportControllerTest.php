<?php

namespace Tests\Feature\Leave;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LeaveReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->app->get('db')->connection()->getDriverName() === 'sqlite') {
            $pdo = $this->app->get('db')->connection()->getPdo();

            $pdo->sqliteCreateFunction(
                'YEAR',
                static fn($date): int => (int) date('Y', strtotime($date)),
            );
        }

        $permission = Permission::create([
            'name' => 'leave_report.view',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'leave-report-tester',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createEmployee(
        ?string $employeeNumber = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): Employee {
        $department = Department::query()->create([
            'code' => fake()->unique()->numerify('DEP####'),
            'name' => 'Test Department',
            'description' => 'Department for testing.',
            'status' => 'active',
        ]);

        $position = Position::query()->create([
            'code' => fake()->unique()->numerify('POS####'),
            'name' => 'Test Position',
            'description' => 'Position for testing.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);

        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => $employeeNumber
                ?? fake()->unique()->numerify('EMP####'),
            'first_name' => $firstName ?? fake()->firstName(),
            'last_name' => $lastName,
            'gender' => 'male',
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
        ]);
    }

    private function createLeaveType(
        ?string $code = null,
        ?string $name = null,
    ): LeaveType {
        return LeaveType::query()->create([
            'name' => $name ?? 'Annual Leave',
            'code' => $code ?? fake()->unique()->numerify('LT####'),
            'default_days' => 12,
            'description' => 'Leave type for testing.',
            'status' => 'active',
        ]);
    }

    private function createBalance(
        Employee $employee,
        LeaveType $leaveType,
        int $year = 2026,
        string $allocatedDays = '12.00',
        string $usedDays = '0.00',
        string $remainingDays = '12.00',
    ): LeaveBalance {
        return LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $year,
            'allocated_days' => $allocatedDays,
            'used_days' => $usedDays,
            'remaining_days' => $remainingDays,
        ]);
    }

    private function createLeaveRequest(
        Employee $employee,
        LeaveType $leaveType,
        string $status,
        string $startDate,
        string $endDate,
        ?string $reason = null,
    ): LeaveRequest {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        return LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $start->diffInDays($end) + 1,
            'reason' => $reason ?? 'Testing leave report.',
            'status' => $status,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_requires_authentication_to_access_leave_report(): void
    {
        $response = $this->getJson('/api/v1/leave-reports');

        $response->assertUnauthorized();
    }

    public function test_requires_leave_report_view_permission(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-reports');

        $response->assertForbidden();
    }

    public function test_can_get_leave_report_list(): void
    {
        $employee = $this->createEmployee(
            firstName: 'John',
            lastName: 'Doe',
        );

        $leaveType = $this->createLeaveType(
            name: 'Annual Leave',
        );

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Report berhasil diambil.'
            )
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'employee_id',
                        'employee_number',
                        'employee_name',
                        'leave_type_id',
                        'leave_type',
                        'year',
                        'allocated_days',
                        'used_days',
                        'remaining_days',
                        'total_requests',
                        'pending_requests',
                        'approved_requests',
                        'rejected_requests',
                        'cancelled_requests',
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

    public function test_returns_leave_balance_data_in_report(): void
    {
        $employee = $this->createEmployee(
            firstName: 'Jane',
            lastName: 'Doe',
        );

        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
            year: 2026,
            allocatedDays: '15.00',
            usedDays: '5.00',
            remainingDays: '10.00',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.employee_id', $employee->id)
            ->assertJsonPath(
                'data.0.employee_number',
                $employee->employee_number
            )
            ->assertJsonPath(
                'data.0.employee_name',
                'Jane Doe'
            )
            ->assertJsonPath(
                'data.0.leave_type_id',
                $leaveType->id
            )
            ->assertJsonPath(
                'data.0.leave_type',
                $leaveType->name
            )
            ->assertJsonPath('data.0.year', 2026)
            ->assertJsonPath(
                'data.0.allocated_days',
                '15.00'
            )
            ->assertJsonPath(
                'data.0.used_days',
                '5.00'
            )
            ->assertJsonPath(
                'data.0.remaining_days',
                '10.00'
            );
    }

    public function test_aggregates_leave_request_counts_by_status(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'pending',
            startDate: '2026-01-01',
            endDate: '2026-01-02',
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'approved',
            startDate: '2026-02-01',
            endDate: '2026-02-02',
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'rejected',
            startDate: '2026-03-01',
            endDate: '2026-03-01',
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'cancelled',
            startDate: '2026-04-01',
            endDate: '2026-04-02',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.total_requests', 4)
            ->assertJsonPath('data.0.pending_requests', 1)
            ->assertJsonPath('data.0.approved_requests', 1)
            ->assertJsonPath('data.0.rejected_requests', 1)
            ->assertJsonPath('data.0.cancelled_requests', 1);
    }

    public function test_returns_zero_request_counts_when_employee_has_no_leave_requests(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.total_requests', 0)
            ->assertJsonPath('data.0.pending_requests', 0)
            ->assertJsonPath('data.0.approved_requests', 0)
            ->assertJsonPath('data.0.rejected_requests', 0)
            ->assertJsonPath('data.0.cancelled_requests', 0);
    }

    public function test_can_filter_report_by_employee(): void
    {
        $employeeA = $this->createEmployee(
            employeeNumber: 'EMP0001',
            firstName: 'Employee',
            lastName: 'A',
        );

        $employeeB = $this->createEmployee(
            employeeNumber: 'EMP0002',
            firstName: 'Employee',
            lastName: 'B',
        );

        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employeeA,
            leaveType: $leaveType,
        );

        $this->createBalance(
            employee: $employeeB,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                "/api/v1/leave-reports?employee_id={$employeeA->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.employee_id',
                $employeeA->id
            );
    }

    public function test_can_filter_report_by_leave_type(): void
    {
        $employee = $this->createEmployee();

        $leaveTypeA = $this->createLeaveType(
            code: 'ANNUAL',
            name: 'Annual Leave',
        );

        $leaveTypeB = $this->createLeaveType(
            code: 'SPECIAL',
            name: 'Special Leave',
        );

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveTypeA,
        );

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveTypeB,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                "/api/v1/leave-reports?leave_type_id={$leaveTypeA->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.leave_type_id',
                $leaveTypeA->id
            );
    }

    public function test_can_filter_report_by_year(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
            year: 2025,
        );

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
            year: 2026,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?year=2026');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.year',
                2026
            );
    }

    public function test_can_filter_report_by_pending_status(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'pending',
            startDate: '2026-01-01',
            endDate: '2026-01-01',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?status=pending');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.pending_requests',
                1
            );
    }

    public function test_can_filter_report_by_approved_status(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'approved',
            startDate: '2026-01-01',
            endDate: '2026-01-01',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?status=approved');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.approved_requests',
                1
            );
    }

    public function test_can_filter_report_by_rejected_status(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'rejected',
            startDate: '2026-01-01',
            endDate: '2026-01-01',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?status=rejected');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.rejected_requests',
                1
            );
    }

    public function test_can_filter_report_by_cancelled_status(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'cancelled',
            startDate: '2026-01-01',
            endDate: '2026-01-01',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?status=cancelled');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.cancelled_requests',
                1
            );
    }

    public function test_can_combine_report_filters(): void
    {
        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $leaveTypeA = $this->createLeaveType();
        $leaveTypeB = $this->createLeaveType();

        $this->createBalance(
            employee: $employeeA,
            leaveType: $leaveTypeA,
            year: 2026,
        );

        $this->createBalance(
            employee: $employeeA,
            leaveType: $leaveTypeB,
            year: 2026,
        );

        $this->createBalance(
            employee: $employeeB,
            leaveType: $leaveTypeA,
            year: 2026,
        );

        $this->createLeaveRequest(
            employee: $employeeA,
            leaveType: $leaveTypeA,
            status: 'approved',
            startDate: '2026-05-01',
            endDate: '2026-05-01',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                "/api/v1/leave-reports?employee_id={$employeeA->id}"
                    . "&leave_type_id={$leaveTypeA->id}"
                    . '&year=2026'
                    . '&status=approved'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.employee_id',
                $employeeA->id
            )
            ->assertJsonPath(
                'data.0.leave_type_id',
                $leaveTypeA->id
            )
            ->assertJsonPath(
                'data.0.year',
                2026
            )
            ->assertJsonPath(
                'data.0.approved_requests',
                1
            );
    }

    public function test_can_paginate_leave_reports(): void
    {
        $employee = $this->createEmployee();

        $leaveTypeA = $this->createLeaveType();
        $leaveTypeB = $this->createLeaveType();
        $leaveTypeC = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveTypeA,
        );

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveTypeB,
        );

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveTypeC,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?per_page=2');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                2
            )
            ->assertJsonPath(
                'meta.pagination.total',
                3
            )
            ->assertJsonPath(
                'meta.pagination.last_page',
                2
            );
    }

    public function test_uses_default_pagination_when_per_page_is_omitted(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                15
            );
    }

    public function test_rejects_invalid_employee_id_filter(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/leave-reports?employee_id=999999'
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_rejects_invalid_leave_type_id_filter(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/leave-reports?leave_type_id=999999'
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['leave_type_id']);
    }

    public function test_rejects_year_below_minimum(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?year=1999');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_rejects_year_above_maximum(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?year=2101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_rejects_invalid_status(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?status=invalid');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_rejects_non_integer_per_page(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?per_page=abc');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_rejects_per_page_below_minimum(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?per_page=0');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_rejects_per_page_above_maximum(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?per_page=101');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_accepts_minimum_valid_year(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
            year: 2000,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?year=2000');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.year',
                2000
            );
    }

    public function test_accepts_maximum_valid_year(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
            year: 2100,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?year=2100');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.year',
                2100
            );
    }

    public function test_returns_empty_data_when_no_leave_balance_matches_filters(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?year=2026');

        $response
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath(
                'meta.pagination.total',
                0
            )
            ->assertJsonPath(
                'meta.pagination.from',
                null
            )
            ->assertJsonPath(
                'meta.pagination.to',
                null
            );
    }

    public function test_does_not_include_report_row_when_status_filter_has_no_matching_request(): void
    {
        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createBalance(
            employee: $employee,
            leaveType: $leaveType,
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            status: 'approved',
            startDate: '2026-01-01',
            endDate: '2026-01-01',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/leave-reports?status=pending');

        $response
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath(
                'meta.pagination.total',
                0
            );
    }

    public function test_only_counts_requests_belonging_to_same_employee_leave_type_and_year(): void
    {
        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $leaveTypeA = $this->createLeaveType();
        $leaveTypeB = $this->createLeaveType();

        $this->createBalance(
            employee: $employeeA,
            leaveType: $leaveTypeA,
            year: 2026,
        );

        $this->createBalance(
            employee: $employeeB,
            leaveType: $leaveTypeA,
            year: 2026,
        );

        $this->createBalance(
            employee: $employeeA,
            leaveType: $leaveTypeB,
            year: 2026,
        );

        $this->createLeaveRequest(
            employee: $employeeA,
            leaveType: $leaveTypeA,
            status: 'approved',
            startDate: '2026-01-01',
            endDate: '2026-01-01',
        );

        $this->createLeaveRequest(
            employee: $employeeB,
            leaveType: $leaveTypeA,
            status: 'pending',
            startDate: '2026-01-01',
            endDate: '2026-01-01',
        );

        $this->createLeaveRequest(
            employee: $employeeA,
            leaveType: $leaveTypeB,
            status: 'rejected',
            startDate: '2026-01-01',
            endDate: '2026-01-01',
        );

        $this->createLeaveRequest(
            employee: $employeeA,
            leaveType: $leaveTypeA,
            status: 'cancelled',
            startDate: '2025-01-01',
            endDate: '2025-01-01',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                "/api/v1/leave-reports?employee_id={$employeeA->id}"
                    . "&leave_type_id={$leaveTypeA->id}"
                    . '&year=2026'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1
            )
            ->assertJsonPath(
                'data.0.total_requests',
                1
            )
            ->assertJsonPath(
                'data.0.pending_requests',
                0
            )
            ->assertJsonPath(
                'data.0.approved_requests',
                1
            )
            ->assertJsonPath(
                'data.0.rejected_requests',
                0
            )
            ->assertJsonPath(
                'data.0.cancelled_requests',
                0
            );
    }
}
