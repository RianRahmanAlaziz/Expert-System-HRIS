<?php

namespace Tests\Unit\Notifications;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\Position;
use App\Models\User;
use App\Notifications\PerformanceReviewRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceReviewRejectedTest extends TestCase
{
    use RefreshDatabase;

    private function createReview(): PerformanceReview
    {
        $user = User::factory()->create();

        $department = Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = Position::query()->create([
            'code' => 'STAFF',
            'name' => 'Staff',
            'description' => 'Staff Position',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
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

        $period = PerformancePeriod::query()->create([
            'name' => 'Performance Period 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
            'description' => 'Performance period 2026',
        ]);

        return PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $user->id,
            'review_type' => 'annual',
            'status' => 'rejected',
            'overall_score' => 70,
            'review_date' => '2026-08-01',
            'comments' => null,
        ]);
    }

    public function test_it_uses_database_and_mail_channels(): void
    {
        $notification = new PerformanceReviewRejected(
            $this->createReview()
        );

        $this->assertSame(
            ['database', 'mail'],
            $notification->via(new User())
        );
    }

    public function test_it_contains_correct_database_data(): void
    {
        $review = $this->createReview();

        $notification = new PerformanceReviewRejected($review);

        $data = $notification->toDatabase(new User());

        $this->assertSame(
            'Performance Review Ditolak',
            $data['title']
        );

        $this->assertSame(
            'Performance review Anda untuk periode Performance Period 2026 telah ditolak.',
            $data['message']
        );

        $this->assertSame(
            $review->id,
            $data['performance_review_id']
        );
    }

    public function test_it_generates_correct_mail(): void
    {
        $review = $this->createReview();

        $notification = new PerformanceReviewRejected($review);

        $mail = $notification->toMail(new User());

        $this->assertSame(
            'Performance Review Ditolak',
            $mail->subject
        );
    }

    public function test_it_can_be_instantiated(): void
    {
        $review = $this->createReview();

        $notification = new PerformanceReviewRejected($review);

        $this->assertSame(
            $review->id,
            $notification->performanceReview->id
        );
    }
}
