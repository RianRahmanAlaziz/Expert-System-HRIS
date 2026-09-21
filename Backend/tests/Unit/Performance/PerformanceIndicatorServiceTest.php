<?php

namespace Tests\Unit\Performance;

use App\Models\ActivityLog;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Services\Performance\PerformanceIndicatorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceIndicatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceIndicatorService $performanceIndicatorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->performanceIndicatorService = app(
            PerformanceIndicatorService::class
        );
    }

    private function createIndicator(
        string $code = 'KPI-COM-001',
        string $name = 'Communication',
        ?string $description = 'Communication performance indicator.',
        ?float $target = 80,
        float $weight = 20,
        ?string $unit = 'score',
        string $status = 'active',
    ): PerformanceIndicator {
        return PerformanceIndicator::query()->create([
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'target' => $target,
            'weight' => $weight,
            'unit' => $unit,
            'status' => $status,
        ]);
    }

    public function test_it_can_get_all_performance_indicators(): void
    {
        $this->createIndicator(
            code: 'KPI-COM-001',
            name: 'Communication',
        );

        $this->createIndicator(
            code: 'KPI-LEAD-001',
            name: 'Leadership',
        );

        $result = $this->performanceIndicatorService->paginate();

        $this->assertSame(2, $result->total());
    }

    public function test_it_can_get_only_active_indicators(): void
    {
        $this->createIndicator(
            code: 'KPI-COM-001',
            name: 'Communication',
            status: 'active',
        );

        $this->createIndicator(
            code: 'KPI-LEAD-001',
            name: 'Leadership',
            status: 'inactive',
        );

        $result = $this->performanceIndicatorService->getActive();

        $this->assertCount(
            1,
            $result,
        );

        $this->assertSame(
            'active',
            $result->first()->status,
        );

        $this->assertEquals(
            'Communication',
            $result->first()->name,
        );
    }

    public function test_it_can_get_indicator_by_id(): void
    {
        $indicator = $this->createIndicator();

        $result = $this->performanceIndicatorService->getById(
            $indicator->id
        );

        $this->assertInstanceOf(
            PerformanceIndicator::class,
            $result
        );

        $this->assertEquals(
            $indicator->id,
            $result->id
        );

        $this->assertEquals(
            'Communication',
            $result->name
        );

        $this->assertEquals(
            80.00,
            (float) $result->target
        );

        $this->assertEquals(
            20.00,
            (float) $result->weight
        );

        $this->assertEquals(
            0,
            $result->review_items_count
        );
    }

    public function test_it_throws_exception_when_indicator_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        $this->performanceIndicatorService->getById(
            999999
        );
    }

    public function test_it_can_get_indicator_with_review_items_count(): void
    {
        $indicator = $this->createIndicator();

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

        $user = User::factory()->create();

        $period = PerformancePeriod::query()->create([
            'name' => 'Performance Review 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'draft',
            'description' => 'Annual performance review.',
        ]);

        $review = PerformanceReview::query()->create([
            'employee_id' => $employee->id,
            'performance_period_id' => $period->id,
            'reviewer_id' => $user->id,
            'status' => 'draft',
            'overall_score' => 80,
            'reviewed_at' => '2026-12-01 00:00:00',
            'comments' => 'Test review.',
        ]);

        PerformanceReviewItem::query()->create([
            'performance_review_id' => $review->id,
            'performance_indicator_id' => $indicator->id,
            'score' => 85,
            'comments' => 'Good performance.',
        ]);

        $result = $this->performanceIndicatorService->getById(
            $indicator->id
        );

        $this->assertEquals(
            1,
            $result->review_items_count
        );
    }

    public function test_it_can_create_performance_indicator(): void
    {
        $indicator = $this->performanceIndicatorService->create([
            'code' => 'KPI-LEAD-001',
            'name' => 'Leadership',
            'description' => 'Leadership performance indicator.',
            'target' => 85,
            'weight' => 25,
            'unit' => 'score',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(
            PerformanceIndicator::class,
            $indicator
        );

        $this->assertEquals(
            'Leadership',
            $indicator->name,
        );

        $this->assertEquals(
            'KPI-LEAD-001',
            $indicator->code,
        );

        $this->assertEquals(
            'score',
            $indicator->unit,
        );

        $this->assertEquals(
            'active',
            $indicator->status,
        );

        $this->assertDatabaseHas('performance_indicators', [
            'id' => $indicator->id,
            'code' => 'KPI-LEAD-001',
            'name' => 'Leadership',
            'status' => 'active',
        ]);
    }

    public function test_it_can_create_indicator_without_optional_fields(): void
    {
        $indicator = $this->performanceIndicatorService->create([
            'code' => 'KPI-ATT-001',
            'name' => 'Attendance',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(
            PerformanceIndicator::class,
            $indicator,
        );

        $this->assertEquals(
            'KPI-ATT-001',
            $indicator->code,
        );

        $this->assertNull(
            $indicator->description,
        );

        $this->assertNull(
            $indicator->target,
        );

        $this->assertEquals(
            0.00,
            (float) $indicator->weight,
        );

        $this->assertNull(
            $indicator->unit,
        );

        $this->assertEquals(
            'active',
            $indicator->status,
        );
    }

    public function test_it_can_update_performance_indicator(): void
    {
        $indicator = $this->createIndicator();

        $result = $this->performanceIndicatorService->update(
            $indicator,
            [
                'name' => 'Effective Communication',
                'description' => 'Updated indicator.',
                'target' => 90,
                'weight' => 30,
                'unit' => 'percentage',
                'status' => 'inactive',
            ],
        );

        $this->assertEquals(
            'Effective Communication',
            $result->name,
        );

        $this->assertEquals(
            'Updated indicator.',
            $result->description,
        );

        $this->assertEquals(
            90.00,
            (float) $result->target,
        );

        $this->assertEquals(
            30.00,
            (float) $result->weight,
        );

        $this->assertEquals(
            'percentage',
            $result->unit,
        );

        $this->assertEquals(
            'inactive',
            $result->status,
        );

        $this->assertDatabaseHas('performance_indicators', [
            'id' => $indicator->id,
            'name' => 'Effective Communication',
            'status' => 'inactive',
        ]);
    }

    public function test_it_can_delete_performance_indicator(): void
    {
        $indicator = $this->createIndicator();

        $this->performanceIndicatorService->delete(
            $indicator
        );

        $this->assertDatabaseMissing('performance_indicators', [
            'id' => $indicator->id,
        ]);
    }

    public function test_it_logs_activity_when_creating_performance_indicator(): void
    {
        $indicator = $this->performanceIndicatorService->create([
            'code' => 'KPI-LEAD-001',
            'name' => 'Leadership',
            'description' => 'Leadership performance indicator.',
            'target' => 85,
            'weight' => 25,
            'unit' => 'score',
            'status' => 'active',
        ]);

        $log = ActivityLog::query()
            ->where('action', 'create')
            ->where('module', 'performance_indicator')
            ->where('target_id', $indicator->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $indicator->getMorphClass(),
            $log->target_type,
        );

        $this->assertSame(
            'Leadership',
            $log->new_values['name'],
        );

        $this->assertSame(
            'score',
            $log->new_values['unit'],
        );

        $this->assertSame(
            'active',
            $log->new_values['status'],
        );
    }

    public function test_it_logs_activity_when_updating_performance_indicator(): void
    {
        $indicator = $this->createIndicator();

        $this->performanceIndicatorService->update(
            $indicator,
            [
                'name' => 'Effective Communication',
                'description' => 'Updated indicator.',
                'target' => 90,
                'weight' => 30,
                'unit' => 'percentage',
                'status' => 'inactive',
            ],
        );

        $log = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'performance_indicator')
            ->where('target_id', $indicator->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'Communication',
            $log->old_values['name'],
        );

        $this->assertSame(
            '80.00',
            (string) $log->old_values['target'],
        );

        $this->assertSame(
            '20.00',
            (string) $log->old_values['weight'],
        );

        $this->assertSame(
            'Effective Communication',
            $log->new_values['name'],
        );

        $this->assertSame(
            '90.00',
            (string) $log->new_values['target'],
        );

        $this->assertSame(
            '30.00',
            (string) $log->new_values['weight'],
        );

        $this->assertEquals(
            'inactive',
            $log->new_values['status'],
        );
    }

    public function test_it_logs_activity_when_deleting_performance_indicator(): void
    {
        $indicator = $this->createIndicator();

        $this->performanceIndicatorService->delete(
            $indicator,
        );

        $log = ActivityLog::query()
            ->where('action', 'delete')
            ->where('module', 'performance_indicator')
            ->where('target_id', $indicator->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'Communication',
            $log->old_values['name'],
        );

        $this->assertSame(
            'score',
            $log->old_values['unit'],
        );

        $this->assertSame(
            'active',
            $log->old_values['status'],
        );
    }
}
