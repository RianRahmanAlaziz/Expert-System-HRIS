<?php

namespace Tests\Unit\Promotion;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\PromotionAssessment;
use App\Models\PromotionAssessmentItem;
use App\Models\User;
use App\Services\Promotion\PromotionAssessmentItemService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PromotionAssessmentItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionAssessmentItemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            PromotionAssessmentItemService::class,
        );
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'DEP-' . uniqid(),
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(
        ?string $code = null,
        ?string $name = null,
    ): Position {
        return Position::query()->create([
            'code' => $code ?? 'POS-' . uniqid(),
            'name' => $name ?? 'Test Position',
            'description' => 'Test position.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(): Employee
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-' . uniqid(),
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
    }

    private function createPromotionAssessment(): PromotionAssessment
    {
        $employee = $this->createEmployee();

        $currentPosition = $this->createPosition(
            'POS-CURRENT-' . uniqid(),
            'Current Position',
        );

        $targetPosition = $this->createPosition(
            'POS-TARGET-' . uniqid(),
            'Target Position',
        );

        $user = User::factory()->create();

        return PromotionAssessment::query()->create([
            'employee_id' => $employee->id,
            'current_position_id' => $currentPosition->id,
            'target_position_id' => $targetPosition->id,
            'assessed_by' => $user->id,
            'assessment_date' => '2026-09-08',
            'status' => 'draft',
            'overall_score' => null,
            'recommendation' => null,
            'notes' => 'Test assessment.',
        ]);
    }

    private function createPromotionAssessmentItem(
        ?PromotionAssessment $assessment = null,
        ?string $criterionCode = null,
        array $attributes = [],
    ): PromotionAssessmentItem {
        $assessment ??= $this->createPromotionAssessment();

        return PromotionAssessmentItem::query()->create(
            array_merge([
                'promotion_assessment_id' => $assessment->id,
                'criterion_type' => 'competency',
                'criterion_code' =>
                $criterionCode ?? 'COMP-' . uniqid(),
                'criterion_name' => 'Communication',
                'score' => 80,
                'weight' => 25,
                'is_passed' => true,
                'notes' => 'Good competency.',
            ], $attributes),
        );
    }

    public function test_it_can_create_promotion_assessment_item(): void
    {
        $assessment = $this->createPromotionAssessment();

        $result = $this->service->create([
            'promotion_assessment_id' => $assessment->id,
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-COMM',
            'criterion_name' => 'Communication',
            'score' => 85,
            'weight' => 25,
            'is_passed' => true,
            'notes' => 'Good communication competency.',
        ]);

        $this->assertInstanceOf(
            PromotionAssessmentItem::class,
            $result,
        );

        $this->assertSame(
            $assessment->id,
            $result->promotion_assessment_id,
        );

        $this->assertSame(
            'competency',
            $result->criterion_type,
        );

        $this->assertSame(
            'COMP-COMM',
            $result->criterion_code,
        );

        $this->assertSame(
            'Communication',
            $result->criterion_name,
        );

        $this->assertSame(
            '85.00',
            (string) $result->score,
        );

        $this->assertSame(
            '25.00',
            (string) $result->weight,
        );

        $this->assertTrue(
            $result->is_passed,
        );

        $this->assertTrue(
            $result->relationLoaded('promotionAssessment'),
        );

        $this->assertDatabaseHas(
            'promotion_assessment_items',
            [
                'id' => $result->id,
                'promotion_assessment_id' => $assessment->id,
                'criterion_code' => 'COMP-COMM',
                'criterion_name' => 'Communication',
            ],
        );
    }

    public function test_it_throws_exception_when_promotion_assessment_item_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class,
        );

        $this->service->findById(999999);
    }

    public function test_it_can_find_promotion_assessment_item_by_id(): void
    {
        $item = $this->createPromotionAssessmentItem();

        $result = $this->service->findById(
            $item->id,
        );

        $this->assertInstanceOf(
            PromotionAssessmentItem::class,
            $result,
        );

        $this->assertSame(
            $item->id,
            $result->id,
        );

        $this->assertTrue(
            $result->relationLoaded('promotionAssessment'),
        );
    }

    public function test_it_can_get_items_by_promotion_assessment(): void
    {
        $assessment = $this->createPromotionAssessment();

        $itemOne = $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'ATTENDANCE',
        );

        $itemTwo = $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'COMPETENCY',
        );

        $result = $this->service
            ->findByPromotionAssessmentId(
                $assessment->id,
            );

        $this->assertCount(
            2,
            $result,
        );

        $this->assertSame(
            $itemOne->id,
            $result[0]->id,
        );

        $this->assertSame(
            $itemTwo->id,
            $result[1]->id,
        );
    }

    public function test_it_can_paginate_promotion_assessment_items(): void
    {
        $assessment = $this->createPromotionAssessment();

        $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'ATTENDANCE',
        );

        $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'COMPETENCY',
        );

        $result = $this->service->paginate(
            perPage: 15,
        );

        $this->assertSame(
            2,
            $result->total(),
        );

        foreach ($result->items() as $item) {
            $this->assertInstanceOf(
                PromotionAssessmentItem::class,
                $item,
            );

            $this->assertTrue(
                $item->relationLoaded('promotionAssessment'),
            );
        }
    }

    public function test_it_can_filter_by_promotion_assessment(): void
    {
        $assessmentOne = $this->createPromotionAssessment();
        $assessmentTwo = $this->createPromotionAssessment();

        $this->createPromotionAssessmentItem(
            assessment: $assessmentOne,
            criterionCode: 'ATTENDANCE',
        );

        $this->createPromotionAssessmentItem(
            assessment: $assessmentTwo,
            criterionCode: 'COMPETENCY',
        );

        $result = $this->service->paginate(
            perPage: 15,
            promotionAssessmentId: $assessmentOne->id,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            $assessmentOne->id,
            $result->items()[0]->promotion_assessment_id,
        );
    }

    public function test_it_can_filter_by_criterion_type(): void
    {
        $assessment = $this->createPromotionAssessment();

        $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'COMPETENCY',
            attributes: [
                'criterion_type' => 'competency',
            ],
        );

        $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'ATTENDANCE',
            attributes: [
                'criterion_type' => 'attendance',
            ],
        );

        $result = $this->service->paginate(
            perPage: 15,
            criterionType: 'attendance',
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            'attendance',
            $result->items()[0]->criterion_type,
        );
    }

    public function test_it_can_filter_by_passed_status(): void
    {
        $assessment = $this->createPromotionAssessment();

        $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'COMPETENCY',
            attributes: [
                'is_passed' => true,
            ],
        );

        $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'ATTENDANCE',
            attributes: [
                'is_passed' => false,
            ],
        );

        $result = $this->service->paginate(
            perPage: 15,
            isPassed: true,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertTrue(
            $result->items()[0]->is_passed,
        );
    }

    public function test_it_rejects_duplicate_criterion_code_in_same_assessment(): void
    {
        $assessment = $this->createPromotionAssessment();

        $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'COMP-COMM',
        );

        $this->expectException(
            ValidationException::class,
        );

        $this->service->create([
            'promotion_assessment_id' => $assessment->id,
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-COMM',
            'criterion_name' => 'Communication',
            'score' => 90,
            'weight' => 25,
            'is_passed' => true,
            'notes' => null,
        ]);
    }

    public function test_it_can_update_promotion_assessment_item(): void
    {
        $item = $this->createPromotionAssessmentItem();

        $result = $this->service->update(
            $item,
            [
                'criterion_name' => 'Leadership',
                'score' => 95,
                'weight' => 30,
                'is_passed' => true,
                'notes' => 'Excellent leadership.',
            ],
        );

        $this->assertSame(
            'Leadership',
            $result->criterion_name,
        );

        $this->assertSame(
            '95.00',
            (string) $result->score,
        );

        $this->assertSame(
            '30.00',
            (string) $result->weight,
        );

        $this->assertSame(
            'Excellent leadership.',
            $result->notes,
        );

        $this->assertDatabaseHas(
            'promotion_assessment_items',
            [
                'id' => $item->id,
                'criterion_name' => 'Leadership',
            ],
        );
    }

    public function test_it_rejects_duplicate_criterion_code_when_updating(): void
    {
        $assessment = $this->createPromotionAssessment();

        $firstItem = $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'COMP-FIRST',
        );

        $secondItem = $this->createPromotionAssessmentItem(
            assessment: $assessment,
            criterionCode: 'COMP-SECOND',
        );

        $this->expectException(
            ValidationException::class,
        );

        $this->service->update(
            $secondItem,
            [
                'criterion_code' => $firstItem->criterion_code,
            ],
        );
    }

    public function test_it_can_delete_promotion_assessment_item(): void
    {
        $item = $this->createPromotionAssessmentItem();

        $this->service->delete($item);

        $this->assertDatabaseMissing(
            'promotion_assessment_items',
            [
                'id' => $item->id,
            ],
        );
    }
}
