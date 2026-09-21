<?php

namespace Tests\Unit\Performance;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Models\Position;
use App\Models\User;
use App\Services\Employee\EmployeeService;
use App\Services\Performance\PerformanceReviewItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PerformanceReviewItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceReviewItemService $performanceReviewItemService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceReviewItemService = app(
            PerformanceReviewItemService::class
        );
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => 'STAFF',
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(): int
    {
        $user = User::factory()->create();

        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeService = app(EmployeeService::class);

        $employee = $employeeService->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => null,
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
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee joined the company.',
        ]);

        return $employee->id;
    }

    private function createPerformancePeriod(): PerformancePeriod
    {
        return PerformancePeriod::query()->create([
            'name' => 'Performance Review 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'description' => 'Annual performance review period.',
        ]);
    }

    private function createReview(
        string $status = 'draft'
    ): PerformanceReview {
        $employeeId = $this->createEmployee();

        $user = User::factory()->create();

        $period = $this->createPerformancePeriod();

        return PerformanceReview::query()->create([
            'employee_id' => $employeeId,
            'performance_period_id' => $period->id,
            'reviewer_id' => $user->id,
            'review_type' => 'annual',
            'status' => $status,
            'overall_score' => 80,
            'review_date' => '2026-12-01',
            'comments' => 'Test review.',
        ]);
    }

    private function createIndicator(
        bool $isActive = true
    ): PerformanceIndicator {
        return PerformanceIndicator::query()->create([
            'code' => 'KPI-' . strtoupper(uniqid()),
            'name' => 'Indicator ' . uniqid(),
            'description' => 'Test performance indicator.',
            'target' => 100,
            'weight' => 20,
            'unit' => 'score',
            'status' => $isActive ? 'active' : 'inactive',
        ]);
    }

    public function test_it_can_get_items_by_review(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator();
        $indicator2 = $this->createIndicator();

        $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator1->id,
            'score' => 80,
            'comments' => 'Good performance.',
        ]);

        $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator2->id,
            'score' => 90,
            'comments' => 'Very good performance.',
        ]);

        $result = $this->performanceReviewItemService->paginateByReview(
            review: $review,
        );

        $this->assertSame(2, $result->total());

        $items = collect($result->items());

        $this->assertTrue(
            $items->every(
                fn(PerformanceReviewItem $item) =>
                $item->relationLoaded('indicator')
            )
        );

        $this->assertTrue(
            $items->contains(
                fn(PerformanceReviewItem $item) =>
                $item->performance_indicator_id === $indicator1->id
            )
        );

        $this->assertTrue(
            $items->contains(
                fn(PerformanceReviewItem $item) =>
                $item->performance_indicator_id === $indicator2->id
            )
        );
    }

    public function test_it_can_get_item_by_id(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 85,
            'comments' => 'Good performance.',
        ]);

        $result = $this->performanceReviewItemService->getById(
            $item
        );

        $this->assertInstanceOf(
            PerformanceReviewItem::class,
            $result
        );

        $this->assertSame(
            $item->id,
            $result->id
        );

        $this->assertTrue(
            $result->relationLoaded('indicator')
        );

        $this->assertSame(
            $indicator->id,
            $result->indicator->id
        );
    }

    public function test_it_can_create_item(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
                'comments' => 'Excellent performance.',
            ]
        );

        $this->assertInstanceOf(
            PerformanceReviewItem::class,
            $item
        );

        $this->assertSame(
            $review->id,
            $item->performance_review_id
        );

        $this->assertSame(
            $indicator->id,
            $item->performance_indicator_id
        );

        $this->assertEquals(
            90,
            $item->score
        );

        $this->assertSame(
            'Excellent performance.',
            $item->comments
        );

        $this->assertTrue(
            $item->relationLoaded('indicator')
        );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
                'performance_review_id' => $review->id,
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
                'comments' => 'Excellent performance.',
            ]
        );
    }

    public function test_it_cannot_create_item_for_approved_review(): void
    {
        $review = $this->createReview('approved');

        $indicator = $this->createIndicator();

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance review yang sudah approved tidak dapat diubah.'
        );

        $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
            ]
        );
    }

    public function test_it_cannot_create_item_with_inactive_indicator(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator(false);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance indicator yang dipilih tidak aktif.'
        );

        $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
            ]
        );
    }

    public function test_it_cannot_create_duplicate_indicator_in_same_review(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comments' => 'Existing item.',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance indicator tersebut sudah digunakan dalam review.'
        );

        $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
                'comments' => 'Duplicate item.',
            ]
        );
    }

    public function test_it_can_update_item(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 70,
            'comments' => 'Initial comment.',
        ]);

        $result = $this->performanceReviewItemService->update(
            $item,
            [
                'score' => 90,
                'comments' => 'Updated comment.',
            ]
        );

        $this->assertSame(
            $item->id,
            $result->id
        );

        $this->assertEquals(
            90,
            $result->score
        );

        $this->assertSame(
            'Updated comment.',
            $result->comments
        );

        $this->assertSame(
            $indicator->id,
            $result->performance_indicator_id
        );

        $this->assertTrue(
            $result->relationLoaded('indicator')
        );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
                'score' => 90,
                'comments' => 'Updated comment.',
            ]
        );
    }

    public function test_it_cannot_update_item_for_approved_review(): void
    {
        $review = $this->createReview('approved');

        $indicator = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comments' => 'Initial comment.',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->expectExceptionMessage(
            'Performance review yang sudah approved tidak dapat diubah.'
        );

        $this->performanceReviewItemService->update(
            $item,
            [
                'score' => 95,
            ]
        );
    }

    public function test_it_can_change_indicator_when_updating_item(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator();
        $indicator2 = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator1->id,
            'score' => 80,
            'comments' => 'Initial comment.',
        ]);

        $result = $this->performanceReviewItemService->update(
            $item,
            [
                'performance_indicator_id' => $indicator2->id,
            ]
        );

        $this->assertSame(
            $indicator2->id,
            $result->performance_indicator_id
        );

        $this->assertTrue(
            $result->relationLoaded('indicator')
        );

        $this->assertSame(
            $indicator2->id,
            $result->indicator->id
        );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
                'performance_indicator_id' => $indicator2->id,
            ]
        );
    }

    public function test_it_cannot_change_to_inactive_indicator(): void
    {
        $review = $this->createReview();

        $activeIndicator = $this->createIndicator();
        $inactiveIndicator = $this->createIndicator(false);

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $activeIndicator->id,
            'score' => 80,
            'comments' => 'Initial comment.',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance indicator yang dipilih tidak aktif.'
        );

        $this->performanceReviewItemService->update(
            $item,
            [
                'performance_indicator_id' => $inactiveIndicator->id,
            ]
        );
    }

    public function test_it_cannot_change_to_duplicate_indicator(): void
    {
        $review = $this->createReview();

        $indicator1 = $this->createIndicator();
        $indicator2 = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator1->id,
            'score' => 80,
            'comments' => 'Initial comment.',
        ]);

        $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator2->id,
            'score' => 90,
            'comments' => 'Existing item.',
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Performance indicator tersebut sudah digunakan dalam review.'
        );

        $this->performanceReviewItemService->update(
            $item,
            [
                'performance_indicator_id' => $indicator2->id,
            ]
        );
    }

    public function test_it_can_delete_item(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comments' => 'To be deleted.',
        ]);

        $this->performanceReviewItemService->delete(
            $item
        );

        $this->assertDatabaseMissing(
            'performance_review_items',
            [
                'id' => $item->id,
            ]
        );
    }

    public function test_it_cannot_delete_item_from_approved_review(): void
    {
        $review = $this->createReview('approved');

        $indicator = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comments' => 'Cannot be deleted.',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->expectExceptionMessage('Performance review yang sudah approved tidak dapat diubah.');

        $this->performanceReviewItemService->delete(
            $item
        );

        $this->assertDatabaseHas(
            'performance_review_items',
            [
                'id' => $item->id,
            ]
        );
    }

    public function test_it_logs_activity_when_creating_item(): void
    {
        $review = $this->createReview();
        $indicator = $this->createIndicator();

        $item = $this->performanceReviewItemService->create(
            $review,
            [
                'performance_indicator_id' => $indicator->id,
                'score' => 90,
                'comments' => 'Excellent performance.',
            ]
        );

        $log = ActivityLog::query()
            ->where('action', 'create')
            ->where('module', 'performance_review_item')
            ->where('target_type', $item->getMorphClass())
            ->where('target_id', $item->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals(
            $review->id,
            $log->new_values['performance_review_id']
        );

        $this->assertEquals(
            $indicator->id,
            $log->new_values['performance_indicator_id']
        );

        $this->assertEquals(
            90,
            $log->new_values['score']
        );

        $this->assertEquals(
            'Excellent performance.',
            $log->new_values['comments']
        );
    }

    public function test_it_logs_activity_when_updating_item(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 70,
            'comments' => 'Initial comment.',
        ]);

        $this->performanceReviewItemService->update(
            $item,
            [
                'score' => 90,
                'comments' => 'Updated comment.',
            ]
        );

        $log = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'performance_review_item')
            ->where('target_type', $item->getMorphClass())
            ->where('target_id', $item->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals(
            70,
            $log->old_values['score']
        );

        $this->assertEquals(
            'Initial comment.',
            $log->old_values['comments']
        );

        $this->assertEquals(
            90,
            $log->new_values['score']
        );

        $this->assertEquals(
            'Updated comment.',
            $log->new_values['comments']
        );
    }

    public function test_it_logs_activity_when_deleting_item(): void
    {
        $review = $this->createReview();

        $indicator = $this->createIndicator();

        $item = $review->performanceReviewItems()->create([
            'performance_indicator_id' => $indicator->id,
            'score' => 80,
            'comments' => 'To be deleted.',
        ]);

        $itemId = $item->id;

        $this->performanceReviewItemService->delete($item);

        $log = ActivityLog::query()
            ->where('action', 'delete')
            ->where('module', 'performance_review_item')
            ->where('target_type', $item->getMorphClass())
            ->where('target_id', $itemId)
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals(
            $review->id,
            $log->old_values['performance_review_id']
        );

        $this->assertEquals(
            $indicator->id,
            $log->old_values['performance_indicator_id']
        );

        $this->assertEquals(
            80,
            $log->old_values['score']
        );

        $this->assertEquals(
            'To be deleted.',
            $log->old_values['comments']
        );
    }
}
