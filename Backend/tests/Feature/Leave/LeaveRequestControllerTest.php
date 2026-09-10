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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_guest_cannot_list_leave_requests(): void
    {
        $response = $this->getJson('/api/v1/leave-requests');

        $response->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_list_leave_requests(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests');

        $response->assertForbidden();
    }

    public function test_user_with_view_permission_can_list_leave_requests(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Daftar Leave Request berhasil diambil.',
            )
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
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

    public function test_leave_request_list_can_filter_by_employee(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employeeOne = $this->createEmployee();
        $employeeTwo = $this->createEmployee();

        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employeeOne,
            leaveType: $leaveType,
        );

        $this->createLeaveRequest(
            employee: $employeeTwo,
            leaveType: $leaveType,
            attributes: [
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-requests?employee_id={$employeeOne->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employeeOne->id,
            );
    }

    public function test_leave_request_list_can_filter_by_leave_type(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee();

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $annual,
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $sick,
            attributes: [
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-requests?leave_type_id={$annual->id}",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.leave_type_id',
                $annual->id,
            );
    }

    public function test_leave_request_list_can_filter_by_year(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee();

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $annual,
            attributes: [
                'start_date' => '2025-05-01',
                'end_date' => '2025-05-02',
            ],
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $sick,
            attributes: [
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests?year=2026');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.start_date',
                '2026-04-30T17:00:00.000000Z',
            );
    }

    public function test_leave_request_list_can_filter_by_status(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee();

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $annual,
            attributes: [
                'status' => 'pending',
            ],
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $sick,
            attributes: [
                'status' => 'approved',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests?status=approved');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_leave_request_list_can_filter_by_multiple_filters(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employeeOne = $this->createEmployee();
        $employeeTwo = $this->createEmployee();

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveRequest(
            employee: $employeeOne,
            leaveType: $annual,
            attributes: [
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-02',
                'status' => 'pending',
            ],
        );

        $this->createLeaveRequest(
            employee: $employeeOne,
            leaveType: $sick,
            attributes: [
                'start_date' => '2026-07-03',
                'end_date' => '2026-07-04',
                'status' => 'approved',
            ],
        );

        $this->createLeaveRequest(
            employee: $employeeTwo,
            leaveType: $annual,
            attributes: [
                'start_date' => '2026-07-05',
                'end_date' => '2026-07-06',
                'status' => 'pending',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-requests"
                    . "?employee_id={$employeeOne->id}"
                    . "&leave_type_id={$annual->id}"
                    . "&year=2026"
                    . "&status=pending",
            );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employeeOne->id,
            )
            ->assertJsonPath(
                'data.0.leave_type_id',
                $annual->id,
            )
            ->assertJsonPath(
                'data.0.status',
                'pending',
            );
    }

    public function test_leave_request_list_can_paginate(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee();

        foreach (range(1, 3) as $index) {
            $leaveType = $this->createLeaveType([
                'code' => "TYPE-{$index}",
            ]);

            $this->createLeaveRequest(
                employee: $employee,
                leaveType: $leaveType,
                attributes: [
                    'start_date' => "2026-0{$index}-01",
                    'end_date' => "2026-0{$index}-02",
                ],
            );
        }

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_leave_request_list_rejects_unknown_employee(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests?employee_id=999999');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'employee_id',
        ]);
    }

    public function test_leave_request_list_rejects_unknown_leave_type(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests?leave_type_id=999999');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'leave_type_id',
        ]);
    }

    public function test_leave_request_list_rejects_invalid_year(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests?year=1999');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'year',
        ]);
    }

    public function test_leave_request_list_rejects_invalid_status(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests?status=pendingg');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'status',
        ]);
    }

    public function test_leave_request_list_rejects_invalid_per_page(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests?per_page=101');

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'per_page',
        ]);
    }

    public function test_guest_cannot_access_my_leave_requests(): void
    {
        $response = $this->getJson('/api/v1/leave-requests/me');

        $response->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_access_my_leave_requests(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests/me');

        $response->assertForbidden();
    }

    public function test_employee_can_access_my_leave_requests(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests/me');

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Request berhasil diambil.',
            )
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employee->id,
            );
    }

    public function test_my_leave_requests_only_return_authenticated_employee_data(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $otherEmployee = $this->createEmployee();

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $annual,
        );

        $this->createLeaveRequest(
            employee: $otherEmployee,
            leaveType: $sick,
            attributes: [
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests/me');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.employee_id',
                $employee->id,
            );
    }

    public function test_my_leave_requests_can_filter_by_status(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $annual,
            attributes: [
                'status' => 'pending',
            ],
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $sick,
            attributes: [
                'status' => 'approved',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests/me?status=approved');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_my_leave_requests_can_filter_by_year(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $annual = $this->createLeaveType([
            'code' => 'ANNUAL',
        ]);

        $sick = $this->createLeaveType([
            'code' => 'SICK',
        ]);

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $annual,
            attributes: [
                'start_date' => '2025-05-01',
                'end_date' => '2025-05-02',
            ],
        );

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $sick,
            attributes: [
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-02',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests/me?year=2026');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.start_date',
                '2026-04-30T17:00:00.000000Z',
            );
    }

    public function test_my_leave_requests_can_paginate(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        foreach (range(1, 3) as $index) {
            $leaveType = $this->createLeaveType([
                'code' => "MY-TYPE-{$index}",
            ]);

            $this->createLeaveRequest(
                employee: $employee,
                leaveType: $leaveType,
                attributes: [
                    'start_date' => "2026-0{$index}-01",
                    'end_date' => "2026-0{$index}-02",
                ],
            );
        }

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests/me?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_my_leave_requests_return_422_when_user_has_no_employee(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests/me');

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'User tidak memiliki data employee.',
            );
    }

    public function test_guest_cannot_show_leave_request(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->getJson(
            "/api/v1/leave-requests/{$leaveRequest->id}",
        );

        $response->assertUnauthorized();
    }

    public function test_user_with_view_permission_can_show_leave_request(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $leaveRequest = $this->createLeaveRequest();

        $response = $this
            ->actingAs($user)
            ->getJson(
                "/api/v1/leave-requests/{$leaveRequest->id}",
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Leave Request berhasil diambil.',
            )
            ->assertJsonPath(
                'data.id',
                $leaveRequest->id,
            );
    }

    public function test_show_unknown_leave_request_returns_404(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.view',
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/leave-requests/999999');

        $response->assertNotFound();
    }

    public function test_guest_cannot_create_leave_request(): void
    {
        $leaveType = $this->createLeaveType();

        $response = $this
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-02',
                'reason' => 'Keperluan pribadi.',
            ]);

        $response->assertUnauthorized();
    }

    public function test_user_without_create_permission_cannot_create_leave_request(): void
    {
        $user = $this->createUser();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => 1,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-02',
                'reason' => 'Keperluan pribadi.',
            ]);

        $response->assertForbidden();
    }

    public function test_employee_can_create_leave_request(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.create',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-03',
                'reason' => 'Keperluan pribadi.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Pengajuan cuti berhasil dibuat.',
            )
            ->assertJsonPath(
                'data.employee_id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.leave_type_id',
                $leaveType->id,
            )
            ->assertJsonPath(
                'data.total_days',
                '3.00',
            )
            ->assertJsonPath(
                'data.status',
                'pending',
            );

        $leaveRequest = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->first();

        $this->assertNotNull($leaveRequest);

        $this->assertEquals(
            '2026-05-01',
            $leaveRequest->start_date->toDateString(),
        );

        $this->assertEquals(
            '2026-05-03',
            $leaveRequest->end_date->toDateString(),
        );

        $this->assertEquals(
            'Keperluan pribadi.',
            $leaveRequest->reason,
        );

        $this->assertEquals(
            'pending',
            $leaveRequest->status,
        );
    }

    public function test_create_leave_request_rejects_missing_required_fields(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.create',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', []);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'leave_type_id',
            'start_date',
            'end_date',
            'reason',
        ]);
    }

    public function test_create_leave_request_rejects_invalid_leave_type(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.create',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => 999999,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-02',
                'reason' => 'Keperluan pribadi.',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'leave_type_id',
        ]);
    }

    public function test_create_leave_request_rejects_end_date_before_start_date(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.create',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-05-10',
                'end_date' => '2026-05-05',
                'reason' => 'Keperluan pribadi.',
            ]);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'end_date',
        ]);
    }

    public function test_create_leave_request_rejects_inactive_leave_type(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.create',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType([
            'status' => 'inactive',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-02',
                'reason' => 'Keperluan pribadi.',
            ]);

        $response->assertNotFound();
    }

    public function test_create_leave_request_rejects_cross_year_dates(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.create',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-12-30',
                'end_date' => '2027-01-02',
                'reason' => 'Keperluan pribadi.',
            ]);

        $response->assertServerError();
    }

    public function test_create_leave_request_rejects_overlapping_pending_request(): void
    {
        $user = $this->createUserWithPermission('leave_request.create');

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'start_date' => '2026-05-05',
                'end_date' => '2026-05-07',
                'status' => 'pending',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-05-06',
                'end_date' => '2026-05-08',
                'reason' => 'Keperluan pribadi.',
            ]);

        $response->assertServerError();
    }

    public function test_create_leave_request_allows_overlap_with_rejected_request(): void
    {
        $user = $this->createUserWithPermission('leave_request.create');

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'start_date' => '2026-05-05',
                'end_date' => '2026-05-07',
                'status' => 'rejected',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-05-06',
                'end_date' => '2026-05-08',
                'reason' => 'Pengajuan baru.',
            ]);

        $response->assertCreated();
    }

    public function test_guest_cannot_approve_leave_request(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve");

        $response->assertUnauthorized();
    }

    public function test_user_without_approve_permission_cannot_approve_leave_request(): void
    {
        $user = $this->createUser();

        $leaveRequest = $this->createLeaveRequest();

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve");

        $response->assertForbidden();
    }

    public function test_user_with_approve_permission_can_approve_leave_request(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.approve',
        );

        $employee = $this->createEmployee();

        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'total_days' => 3,
            ],
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'allocated_days' => 12,
                'used_days' => 2,
                'remaining_days' => 10,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Pengajuan cuti berhasil disetujui.',
            )
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $user->id);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'approved_by' => $user->id,
            'rejection_reason' => null,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'used_days' => 5.00,
            'remaining_days' => 7.00,
        ]);
    }

    public function test_approve_leave_request_rejects_non_pending_request(): void
    {
        $user = $this->createUserWithPermission('leave_request.approve');

        $leaveRequest = $this->createLeaveRequest(
            attributes: [
                'status' => 'rejected',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve");

        $response->assertServerError();
    }

    public function test_approve_leave_request_rejects_when_leave_balance_missing(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.approve',
        );

        $leaveRequest = $this->createLeaveRequest();

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve");

        $response->assertServerError();
    }

    public function test_approve_leave_request_rejects_when_balance_is_insufficient(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.approve',
        );

        $employee = $this->createEmployee();
        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'total_days' => 5,
            ],
        );

        $this->createLeaveBalance(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'allocated_days' => 3,
                'used_days' => 2,
                'remaining_days' => 1,
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve");

        $response->assertServerError();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_guest_cannot_reject_leave_request(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->postJson(
            "/api/v1/leave-requests/{$leaveRequest->id}/reject",
            [
                'rejection_reason' => 'Tidak dapat disetujui.',
            ],
        );

        $response->assertUnauthorized();
    }

    public function test_user_without_reject_permission_cannot_reject_leave_request(): void
    {
        $user = $this->createUser();

        $leaveRequest = $this->createLeaveRequest();

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/leave-requests/{$leaveRequest->id}/reject",
                [
                    'rejection_reason' => 'Tidak dapat disetujui.',
                ],
            );

        $response->assertForbidden();
    }

    public function test_user_with_reject_permission_can_reject_leave_request(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.reject',
        );

        $leaveRequest = $this->createLeaveRequest();

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/leave-requests/{$leaveRequest->id}/reject",
                [
                    'rejection_reason' => 'Kuota cuti tidak dapat diberikan.',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Pengajuan cuti berhasil ditolak.',
            )
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath(
                'data.rejection_reason',
                'Kuota cuti tidak dapat diberikan.',
            )
            ->assertJsonPath(
                'data.approved_by',
                $user->id,
            );

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => null,
            'rejection_reason' => 'Kuota cuti tidak dapat diberikan.',
        ]);
    }

    public function test_reject_leave_request_requires_rejection_reason(): void
    {
        $user = $this->createUserWithPermission('leave_request.reject');

        $leaveRequest = $this->createLeaveRequest();

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/leave-requests/{$leaveRequest->id}/reject",
                [],
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'rejection_reason',
        ]);
    }

    public function test_reject_leave_request_rejects_non_pending_request(): void
    {
        $user = $this->createUserWithPermission('leave_request.reject');

        $leaveRequest = $this->createLeaveRequest(
            attributes: [
                'status' => 'approved',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                "/api/v1/leave-requests/{$leaveRequest->id}/reject",
                [
                    'rejection_reason' => 'Tidak disetujui.',
                ],
            );

        $response->assertServerError();
    }

    public function test_guest_cannot_cancel_leave_request(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $response = $this->postJson("/api/v1/leave-requests/{$leaveRequest->id}/cancel");

        $response->assertUnauthorized();
    }

    public function test_user_without_cancel_permission_cannot_cancel_leave_request(): void
    {
        $user = $this->createUser();

        $leaveRequest = $this->createLeaveRequest();

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/cancel");

        $response->assertForbidden();
    }

    public function test_employee_can_cancel_own_pending_leave_request(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.cancel',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/cancel");

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Pengajuan cuti berhasil dibatalkan.',
            )
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'cancelled',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_employee_cannot_cancel_other_employee_leave_request(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.cancel',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $otherEmployee = $this->createEmployee();

        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $otherEmployee,
            leaveType: $leaveType,
        );

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/cancel");

        $response->assertServerError();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_cancel_leave_request_rejects_non_pending_request(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.cancel',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $leaveType = $this->createLeaveType();

        $leaveRequest = $this->createLeaveRequest(
            employee: $employee,
            leaveType: $leaveType,
            attributes: [
                'status' => 'approved',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/cancel");

        $response->assertServerError();
    }

    public function test_cancel_leave_request_returns_422_when_user_has_no_employee(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.cancel',
        );

        $leaveRequest = $this->createLeaveRequest();

        $response = $this
            ->actingAs($user)
            ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/cancel");

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'User tidak memiliki data employee.',
            );
    }

    public function test_approve_unknown_leave_request_returns_404(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.approve',
        );

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests/999999/approve');

        $response->assertNotFound();
    }

    public function test_reject_unknown_leave_request_returns_404(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.reject',
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/leave-requests/999999/reject',
                [
                    'rejection_reason' => 'Tidak disetujui.',
                ],
            );

        $response->assertNotFound();
    }

    public function test_cancel_unknown_leave_request_returns_404(): void
    {
        $user = $this->createUserWithPermission(
            'leave_request.cancel',
        );

        $employee = $this->createEmployee([
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/leave-requests/999999/cancel');

        $response->assertNotFound();
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createUserWithPermission(string $permission): User
    {
        $user = $this->createUser();

        $permissionModel = Permission::findOrCreate(
            $permission,
            'web',
        );

        $role = Role::findOrCreate(
            'test-role-' . uniqid(),
            'web',
        );

        $role->givePermissionTo($permissionModel);

        $user->assignRole($role);

        return $user;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEmployee(array $attributes = []): Employee
    {
        $department = Department::query()->create([
            'code' => 'DEPT-' . uniqid(),
            'name' => 'Human Resources',
            'description' => null,
            'status' => 'active',
        ]);

        $position = Position::query()->create([
            'code' => 'POS-' . uniqid(),
            'name' => 'Staff',
            'description' => null,
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);

        return Employee::query()->create(array_merge([
            'user_id' => null,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => null,
            'employee_number' => 'EMP-' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'gender' => 'male',
            'birth_date' => null,
            'phone' => null,
            'address' => null,
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createLeaveType(array $attributes = []): LeaveType
    {
        return LeaveType::query()->create(array_merge([
            'name' => 'Cuti Tahunan',
            'code' => 'ANNUAL-' . uniqid(),
            'default_days' => 12,
            'description' => 'Cuti tahunan karyawan.',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createLeaveRequest(
        ?Employee $employee = null,
        ?LeaveType $leaveType = null,
        array $attributes = [],
    ): LeaveRequest {
        $employee ??= $this->createEmployee();
        $leaveType ??= $this->createLeaveType();

        return LeaveRequest::query()->create(array_merge([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'total_days' => 3.00,
            'reason' => 'Keperluan pribadi.',
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ], $attributes));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createLeaveBalance(
        Employee $employee,
        LeaveType $leaveType,
        array $attributes = [],
    ): LeaveBalance {
        return LeaveBalance::query()->create(array_merge([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'allocated_days' => 12.00,
            'used_days' => 2.00,
            'remaining_days' => 10.00,
        ], $attributes));
    }
}
