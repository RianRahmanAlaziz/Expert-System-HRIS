<?php

namespace Tests\Unit\Notifications;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use App\Notifications\LeaveRequestRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestRejectedTest extends TestCase
{
    use RefreshDatabase;

    private function createLeaveRequest(): LeaveRequest
    {
        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = Position::query()->create([
            'code' => 'STAFF',
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);

        $leaveType = LeaveType::query()->create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'default_days' => 12,
            'description' => 'Annual Leave description.',
            'status' => 'active',
        ]);

        return LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-12',
            'total_days' => 3,
            'reason' => 'Personal leave.',
            'status' => 'rejected',
            'rejection_reason' => 'Project deadline.',
        ]);
    }

    public function test_it_uses_database_channel(): void
    {
        $notification = new LeaveRequestRejected(
            $this->createLeaveRequest()
        );

        $this->assertSame(
            ['database', 'mail'],
            $notification->via(new User())
        );
    }

    public function test_it_contains_correct_database_data(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $notification = new LeaveRequestRejected($leaveRequest);

        $data = $notification->toDatabase(new User());

        $this->assertSame(
            'Pengajuan Cuti Ditolak',
            $data['title']
        );

        $this->assertSame(
            'Pengajuan cuti Annual Leave Anda telah ditolak.',
            $data['message']
        );

        $this->assertSame(
            $leaveRequest->id,
            $data['leave_request_id']
        );

        $this->assertSame(
            'Project deadline.',
            $data['rejection_reason']
        );
    }

    public function test_it_generates_correct_mail(): void
    {
        $notification = new LeaveRequestRejected(
            $this->createLeaveRequest()
        );

        $mail = $notification->toMail(new User());

        $this->assertSame(
            'Pengajuan Cuti Ditolak',
            $mail->subject
        );
    }

    public function test_it_can_be_instantiated(): void
    {
        $leaveRequest = $this->createLeaveRequest();

        $notification = new LeaveRequestRejected($leaveRequest);

        $this->assertSame(
            $leaveRequest->id,
            $notification->leaveRequest->id
        );
    }
}
