<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createUserWithPermission(string $permission): User
    {
        $user = User::factory()->create();

        $permission = Permission::findOrCreate(
            $permission,
            'web',
        );

        $role = Role::create([
            'name' => 'test-role-' . uniqid(),
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        return $user;
    }

    private function createUserWithPermissions(
        array $permissions,
    ): User {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'test-role-' . uniqid(),
            'guard_name' => 'web',
        ]);

        foreach ($permissions as $permission) {
            $permission = Permission::findOrCreate(
                $permission,
                'web',
            );

            $role->givePermissionTo($permission);
        }

        $user->assignRole($role);

        return $user;
    }

    private function createDepartment(array $overrides = []): Department
    {
        return Department::query()->create(
            array_merge([
                'code' => 'DEP-' . strtoupper(substr(uniqid(), -6)),
                'name' => 'Technology',
                'description' => 'Technology Department',
                'status' => 'active',
                'is_active' => true,
            ], $overrides),
        );
    }

    private function createPosition(array $overrides = []): Position
    {
        return Position::query()->create(
            array_merge([
                'code' => 'POS-' . strtoupper(substr(uniqid(), -6)),
                'name' => 'Software Engineer',
                'description' => 'Software Engineer Position',
                'level' => 5,
                'status' => 'active',
                'is_active' => true,
            ], $overrides),
        );
    }

    private function createEmployee(
        ?User $user = null,
        array $overrides = [],
    ): Employee {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        return Employee::query()->create(
            array_merge([
                'user_id' => $user?->id,
                'department_id' => $department->id,
                'position_id' => $position->id,
                'manager_id' => null,
                'employee_number' => 'EMP-' . strtoupper(substr(uniqid(), -6)),
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'male',
                'birth_date' => '1995-01-15',
                'phone' => '08123456789',
                'address' => 'Jakarta',
                'join_date' => '2025-01-01',
                'employment_type' => 'full_time',
                'employment_status' => 'active',
            ], $overrides),
        );
    }

    private function createAttendance(
        Employee $employee,
        array $overrides = [],
    ): Attendance {
        return Attendance::query()->create(
            array_merge([
                'employee_id' => $employee->id,
                'attendance_date' => '2026-09-01',
                'clock_in' => '2026-09-01 08:00:00',
                'clock_out' => '2026-09-01 17:00:00',
                'status' => 'present',
                'late_minutes' => 0,
                'working_minutes' => 540,
                'notes' => null,
            ], $overrides),
        );
    }

    public function test_guest_cannot_clock_in(): void
    {
        $response = $this->postJson('/api/v1/attendances/clock-in');

        $response->assertUnauthorized();
    }

    public function test_user_without_clock_in_permission_cannot_clock_in(): void
    {
        $user = User::factory()->create();

        $this->createEmployee($user);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/attendances/clock-in');

        $response->assertForbidden();
    }

    public function test_employee_can_clock_in(): void
    {
        $workStart = Carbon::parse(config('attendance.work_start'));

        Carbon::setTestNow($workStart);

        $user = $this->createUserWithPermission('attendance.clock_in');
        $employee = $this->createEmployee(user: $user);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/attendances/clock-in');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Berhasil Clock in.')
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.late_minutes', 0);

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->first();

        $this->assertNotNull($attendance);
        $this->assertEquals(
            $workStart->toDateString(),
            $attendance->attendance_date->toDateString(),
        );
        $this->assertEquals('present', $attendance->status);
        $this->assertEquals(0, $attendance->late_minutes);
    }

    public function test_clock_in_marks_employee_late_when_after_work_start(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.clock_in',
        );

        $employee = $this->createEmployee($user);

        $workStart = Carbon::parse(
            now()->toDateString() . ' ' . config('attendance.work_start'),
        );

        Carbon::setTestNow(
            $workStart->copy()->addMinutes(30),
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/attendances/clock-in');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'late',
            )
            ->assertJsonPath(
                'data.late_minutes',
                30,
            );

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'status' => 'late',
            'late_minutes' => 30,
        ]);
    }

    public function test_employee_cannot_clock_in_twice_on_same_day(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.clock_in',
        );

        $employee = $this->createEmployee($user);

        $workStart = Carbon::parse(
            now()->toDateString() . ' ' . config('attendance.work_start'),
        );

        Carbon::setTestNow($workStart);

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => $workStart->toDateString(),
                'clock_in' => $workStart,
                'clock_out' => null,
                'status' => 'present',
                'late_minutes' => 0,
                'working_minutes' => 0,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/attendances/clock-in');

        $response->assertServerError();

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_guest_cannot_clock_out(): void
    {
        $response = $this->postJson('/api/v1/attendances/clock-out');

        $response->assertUnauthorized();
    }

    public function test_user_without_clock_out_permission_cannot_clock_out(): void
    {
        $user = User::factory()->create();

        $this->createEmployee($user);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/attendances/clock-out');

        $response->assertForbidden();
    }

    public function test_employee_can_clock_out(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.clock_out',
        );

        $employee = $this->createEmployee($user);

        $clockIn = now()->startOfDay()->setTime(8, 0);
        $clockOut = $clockIn->copy()->addHours(9);

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => $clockIn->toDateString(),
                'clock_in' => $clockIn,
                'clock_out' => null,
                'working_minutes' => 0,
            ],
        );

        Carbon::setTestNow($clockOut);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/attendances/clock-out');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.employee_id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.working_minutes',
                540,
            );

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'working_minutes' => 540,
        ]);
    }

    public function test_employee_cannot_clock_out_without_clock_in(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.clock_out',
        );

        $this->createEmployee($user);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/attendances/clock-out');

        $response->assertNotFound();
    }

    public function test_employee_cannot_clock_out_twice(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.clock_out',
        );

        $employee = $this->createEmployee($user);

        $clockIn = now()->startOfDay()->setTime(8, 0);
        $clockOut = $clockIn->copy()->addHours(9);

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => $clockIn->toDateString(),
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'working_minutes' => 540,
            ],
        );

        Carbon::setTestNow($clockOut->copy()->addHour());

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/attendances/clock-out');

        $response->assertServerError();
    }

    public function test_guest_cannot_list_attendances(): void
    {
        $response = $this->getJson('/api/v1/attendances');

        $response->assertUnauthorized();
    }

    public function test_user_without_attendance_view_permission_cannot_list_attendances(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances');

        $response->assertForbidden();
    }

    public function test_employee_can_list_only_their_own_attendances(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.view',
        );

        $employee = $this->createEmployee($user);
        $otherEmployee = $this->createEmployee();

        $this->createAttendance($employee);
        $this->createAttendance(
            $otherEmployee,
            [
                'attendance_date' => '2026-09-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.employee_id',
                $employee->id,
            );
    }

    public function test_user_with_view_all_can_list_all_attendances(): void
    {
        $user = $this->createUserWithPermissions([
            'attendance.view',
            'attendance.view_all',
        ]);

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $this->createAttendance($employeeA);
        $this->createAttendance(
            $employeeB,
            [
                'attendance_date' => '2026-09-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                2,
            );
    }

    public function test_employee_list_can_filter_by_date_range(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.view',
        );

        $employee = $this->createEmployee($user);

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => '2026-09-01',
            ],
        );

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => '2026-09-05',
            ],
        );

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => '2026-09-10',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances?start_date=2026-09-02&end_date=2026-09-09');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            );
    }

    public function test_employee_list_can_filter_by_status(): void
    {
        $user = $this->createUserWithPermission('attendance.view');

        $employee = $this->createEmployee($user);

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => '2026-09-01',
                'status' => 'present',
            ],
        );

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => '2026-09-02',
                'status' => 'late',
                'late_minutes' => 20,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances?status=late');

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            );
    }

    public function test_user_with_view_all_can_filter_by_employee(): void
    {
        $user = $this->createUserWithPermissions([
            'attendance.view',
            'attendance.view_all',
        ]);

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $this->createAttendance($employeeA);
        $this->createAttendance(
            $employeeB,
            [
                'attendance_date' => '2026-09-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/attendances?employee_id={$employeeA->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.total',
                1,
            )
            ->assertJsonPath(
                'data.0.employee_id',
                $employeeA->id,
            );
    }

    public function test_attendance_list_rejects_invalid_status(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.view',
        );

        $employee = $this->createEmployee($user);

        $response = $this
            ->actingAs($user)
            ->getJson(
                '/api/v1/attendances?status=invalid',
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_attendance_list_rejects_invalid_date_range(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.view',
        );

        $employee = $this->createEmployee($user);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances?start_date=2026-09-10&end_date=2026-09-01');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'end_date',
            ]);
    }

    public function test_attendance_list_rejects_unknown_employee(): void
    {
        $user = $this->createUserWithPermission('attendance.view');

        $this->createEmployee($user);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances?employee_id=999999');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_id',
            ]);
    }

    public function test_employee_can_show_their_own_attendance(): void
    {
        $user = $this->createUserWithPermission('attendance.view');

        $employee = $this->createEmployee($user);

        $attendance = $this->createAttendance($employee);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/attendances/{$attendance->id}");

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $attendance->id,
            )
            ->assertJsonPath(
                'data.employee_id',
                $employee->id,
            );
    }

    public function test_employee_cannot_show_other_employee_attendance(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.view',
        );

        $employee = $this->createEmployee($user);
        $otherEmployee = $this->createEmployee();

        $attendance = $this->createAttendance($otherEmployee);

        $response = $this
            ->actingAs($user)
            ->getJson("/api/v1/attendances/{$attendance->id}");

        $response->assertNotFound();
    }

    public function test_user_with_view_all_can_show_other_employee_attendance(): void
    {
        $user = $this->createUserWithPermissions([
            'attendance.view',
            'attendance.view_all',
        ]);

        $employee = $this->createEmployee();
        $attendance = $this->createAttendance($employee);

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/attendances/{$attendance->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $attendance->id,
            );
    }

    public function test_attendance_show_returns_404_for_unknown_attendance(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.view',
        );

        $employee = $this->createEmployee($user);

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances/999999');

        $response->assertNotFound();
    }

    public function test_guest_cannot_access_attendance_recap(): void
    {
        $response = $this->getJson('/api/v1/attendances/recap');

        $response->assertUnauthorized();
    }

    public function test_user_without_view_all_permission_cannot_access_attendance_recap(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances/recap');

        $response->assertForbidden();
    }

    public function test_user_with_view_all_can_get_attendance_recap(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.view_all',
        );

        $employee = $this->createEmployee();

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => '2026-09-01',
                'status' => 'present',
                'late_minutes' => 0,
                'working_minutes' => 540,
            ],
        );

        $this->createAttendance(
            $employee,
            [
                'attendance_date' => '2026-09-02',
                'status' => 'late',
                'late_minutes' => 20,
                'working_minutes' => 500,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances/recap');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.total_days',
                2,
            )
            ->assertJsonPath(
                'data.present',
                1,
            )
            ->assertJsonPath(
                'data.late',
                1,
            )
            ->assertJsonPath(
                'data.absent',
                0,
            )
            ->assertJsonPath(
                'data.total_late_minutes',
                20,
            )
            ->assertJsonPath(
                'data.total_working_minutes',
                1040,
            );
    }

    public function test_user_with_view_all_can_filter_attendance_recap(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.view_all',
        );

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $this->createAttendance(
            $employeeA,
            [
                'attendance_date' => '2026-09-01',
            ],
        );

        $this->createAttendance(
            $employeeB,
            [
                'attendance_date' => '2026-09-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/attendances/recap?employee_id={$employeeA->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.total_days',
                1,
            );
    }

    public function test_guest_cannot_access_attendance_report(): void
    {
        $response = $this->getJson('/api/v1/attendances/report');

        $response->assertUnauthorized();
    }

    public function test_user_without_report_permission_cannot_access_attendance_report(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances/report');

        $response->assertForbidden();
    }

    public function test_user_with_report_permission_can_get_attendance_report(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.report',
        );

        $employeeA = $this->createEmployee(
            overrides: [
                'employee_number' => 'EMP-001',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ],
        );

        $employeeB = $this->createEmployee(
            overrides: [
                'employee_number' => 'EMP-002',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
            ],
        );

        $this->createAttendance(
            $employeeA,
            [
                'attendance_date' => '2026-09-01',
                'status' => 'present',
                'late_minutes' => 0,
                'working_minutes' => 540,
            ],
        );

        $this->createAttendance(
            $employeeA,
            [
                'attendance_date' => '2026-09-02',
                'status' => 'late',
                'late_minutes' => 15,
                'working_minutes' => 525,
            ],
        );

        $this->createAttendance(
            $employeeB,
            [
                'attendance_date' => '2026-09-01',
                'status' => 'absent',
                'late_minutes' => 0,
                'working_minutes' => 0,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances/report');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $report = collect($response->json('data'));

        $employeeAReport = $report->firstWhere(
            'employee_id',
            $employeeA->id,
        );

        $this->assertNotNull($employeeAReport);
        $this->assertSame(1, $employeeAReport['present']);
        $this->assertSame(1, $employeeAReport['late']);
        $this->assertSame(0, $employeeAReport['absent']);
        $this->assertSame(15, $employeeAReport['total_late_minutes']);
        $this->assertSame(1065, $employeeAReport['total_working_minutes']);
    }

    public function test_attendance_report_can_filter_by_employee(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.report',
        );

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $this->createAttendance($employeeA);

        $this->createAttendance(
            $employeeB,
            [
                'attendance_date' => '2026-09-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/attendances/report?employee_id={$employeeA->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employeeA->id,
            );
    }

    public function test_attendance_report_rejects_invalid_status(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.report',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances/report?status=invalid');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_attendance_report_rejects_invalid_date_range(): void
    {
        $user = $this->createUserWithPermission(
            'attendance.report',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/attendances/report?start_date=2026-09-10&end_date=2026-09-01');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'end_date',
            ]);
    }
}
