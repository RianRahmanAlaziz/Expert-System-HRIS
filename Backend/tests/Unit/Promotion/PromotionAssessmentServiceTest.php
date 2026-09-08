<?php

namespace Tests\Unit\Promotion;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\PromotionAssessment;
use App\Models\User;
use App\Services\Promotion\PromotionAssessmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionAssessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionAssessmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(
            PromotionAssessmentService::class,
        );
    }

    private function createUser(): User
    {
        return User::factory()->create();
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

    private function createEmployee(
        ?User $user = null,
        ?Position $position = null,
    ): Employee {
        $department = $this->createDepartment();

        return Employee::query()->create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'position_id' => $position?->id
                ?? $this->createPosition()->id,
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

    private function createPromotionAssessment(
        ?Employee $employee = null,
        ?Position $currentPosition = null,
        ?Position $targetPosition = null,
        ?User $assessedBy = null,
        array $attributes = [],
    ): PromotionAssessment {
        $employee ??= $this->createEmployee();

        $currentPosition ??= $this->createPosition(
            'POS-CURRENT-' . uniqid(),
            'Current Position',
        );

        $targetPosition ??= $this->createPosition(
            'POS-TARGET-' . uniqid(),
            'Target Position',
        );

        $assessedBy ??= $this->createUser();

        return PromotionAssessment::query()->create(
            array_merge([
                'employee_id' => $employee->id,
                'current_position_id' => $currentPosition->id,
                'target_position_id' => $targetPosition->id,
                'assessed_by' => $assessedBy->id,
                'assessment_date' => '2026-09-08',
                'status' => 'draft',
                'overall_score' => null,
                'recommendation' => null,
                'notes' => 'Test assessment.',
            ], $attributes),
        );
    }

    public function test_it_can_create_promotion_assessment(): void
    {
        $employeeUser = $this->createUser();
        $assessedBy = $this->createUser();

        $currentPosition = $this->createPosition(
            'POS-CURRENT',
            'Current Position',
        );

        $targetPosition = $this->createPosition(
            'POS-TARGET',
            'Target Position',
        );

        $employee = $this->createEmployee(
            $employeeUser,
            $currentPosition,
        );

        $result = $this->service->create(
            $assessedBy,
            [
                'employee_id' => $employee->id,
                'current_position_id' => $currentPosition->id,
                'target_position_id' => $targetPosition->id,
                'assessment_date' => '2026-09-08',
                'status' => 'draft',
                'overall_score' => 85.50,
                'recommendation' => 'promote',
                'notes' => 'Ready for promotion.',
            ],
        );

        $this->assertInstanceOf(
            PromotionAssessment::class,
            $result,
        );

        $this->assertSame(
            $employee->id,
            $result->employee_id,
        );

        $this->assertSame(
            $currentPosition->id,
            $result->current_position_id,
        );

        $this->assertSame(
            $targetPosition->id,
            $result->target_position_id,
        );

        $this->assertSame(
            $assessedBy->id,
            $result->assessed_by,
        );

        $this->assertSame(
            '2026-09-08',
            $result->assessment_date->format('Y-m-d'),
        );

        $this->assertSame(
            'draft',
            $result->status,
        );

        $this->assertSame(
            '85.50',
            (string) $result->overall_score,
        );

        $this->assertSame(
            'promote',
            $result->recommendation,
        );

        $this->assertTrue(
            $result->relationLoaded('employee'),
        );

        $this->assertTrue(
            $result->relationLoaded('currentPosition'),
        );

        $this->assertTrue(
            $result->relationLoaded('targetPosition'),
        );

        $this->assertTrue(
            $result->relationLoaded('assessedBy'),
        );

        $this->assertTrue(
            $result->relationLoaded('items'),
        );

        $this->assertDatabaseHas(
            'promotion_assessments',
            [
                'id' => $result->id,
                'employee_id' => $employee->id,
                'current_position_id' => $currentPosition->id,
                'target_position_id' => $targetPosition->id,
                'assessed_by' => $assessedBy->id,
                'status' => 'draft',
                'recommendation' => 'promote',
            ],
        );
    }

    public function test_it_can_find_promotion_assessment_by_id(): void
    {
        $assessment = $this->createPromotionAssessment();

        $result = $this->service->findById(
            $assessment->id,
        );

        $this->assertInstanceOf(
            PromotionAssessment::class,
            $result,
        );

        $this->assertSame(
            $assessment->id,
            $result->id,
        );

        $this->assertTrue(
            $result->relationLoaded('employee'),
        );

        $this->assertTrue(
            $result->relationLoaded('currentPosition'),
        );

        $this->assertTrue(
            $result->relationLoaded('targetPosition'),
        );

        $this->assertTrue(
            $result->relationLoaded('assessedBy'),
        );

        $this->assertTrue(
            $result->relationLoaded('items'),
        );
    }

    public function test_it_throws_exception_when_promotion_assessment_is_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class,
        );

        $this->service->findById(999999);
    }

    public function test_it_can_paginate_promotion_assessments(): void
    {
        $this->createPromotionAssessment();
        $this->createPromotionAssessment();

        $result = $this->service->paginate(
            perPage: 15,
        );

        $this->assertSame(
            2,
            $result->total(),
        );

        foreach ($result->items() as $item) {
            $this->assertInstanceOf(
                PromotionAssessment::class,
                $item,
            );

            $this->assertTrue(
                $item->relationLoaded('employee'),
            );

            $this->assertTrue(
                $item->relationLoaded('currentPosition'),
            );

            $this->assertTrue(
                $item->relationLoaded('targetPosition'),
            );

            $this->assertTrue(
                $item->relationLoaded('assessedBy'),
            );
        }
    }

    public function test_it_can_filter_by_employee(): void
    {
        $employeeOne = $this->createEmployee();
        $employeeTwo = $this->createEmployee();

        $this->createPromotionAssessment(
            employee: $employeeOne,
        );

        $this->createPromotionAssessment(
            employee: $employeeTwo,
        );

        $result = $this->service->paginate(
            perPage: 15,
            employeeId: $employeeOne->id,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            $employeeOne->id,
            $result->items()[0]->employee_id,
        );
    }

    public function test_it_can_filter_by_current_position(): void
    {
        $currentPosition = $this->createPosition();

        $this->createPromotionAssessment(
            currentPosition: $currentPosition,
        );

        $this->createPromotionAssessment();

        $result = $this->service->paginate(
            perPage: 15,
            currentPositionId: $currentPosition->id,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            $currentPosition->id,
            $result->items()[0]->current_position_id,
        );
    }

    public function test_it_can_filter_by_target_position(): void
    {
        $targetPosition = $this->createPosition();

        $this->createPromotionAssessment(
            targetPosition: $targetPosition,
        );

        $this->createPromotionAssessment();

        $result = $this->service->paginate(
            perPage: 15,
            targetPositionId: $targetPosition->id,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            $targetPosition->id,
            $result->items()[0]->target_position_id,
        );
    }

    public function test_it_can_filter_by_assessed_by(): void
    {
        $assessedBy = $this->createUser();

        $this->createPromotionAssessment(
            assessedBy: $assessedBy,
        );

        $this->createPromotionAssessment();

        $result = $this->service->paginate(
            perPage: 15,
            assessedBy: $assessedBy->id,
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            $assessedBy->id,
            $result->items()[0]->assessed_by,
        );
    }

    public function test_it_can_filter_by_status(): void
    {
        $this->createPromotionAssessment(
            attributes: [
                'status' => 'draft',
            ],
        );

        $this->createPromotionAssessment(
            attributes: [
                'status' => 'completed',
            ],
        );

        $result = $this->service->paginate(
            perPage: 15,
            status: 'completed',
        );

        $this->assertSame(
            1,
            $result->total(),
        );

        $this->assertSame(
            'completed',
            $result->items()[0]->status,
        );
    }

    public function test_it_can_update_promotion_assessment(): void
    {
        $assessment = $this->createPromotionAssessment();

        $result = $this->service->update(
            $assessment,
            [
                'status' => 'completed',
                'overall_score' => 90,
                'recommendation' => 'promote',
                'notes' => 'Assessment completed.',
            ],
        );

        $this->assertSame(
            'completed',
            $result->status,
        );

        $this->assertSame(
            '90.00',
            (string) $result->overall_score,
        );

        $this->assertSame(
            'promote',
            $result->recommendation,
        );

        $this->assertSame(
            'Assessment completed.',
            $result->notes,
        );

        $this->assertDatabaseHas(
            'promotion_assessments',
            [
                'id' => $assessment->id,
                'status' => 'completed',
                'recommendation' => 'promote',
            ],
        );
    }

    public function test_it_can_delete_promotion_assessment(): void
    {
        $assessment = $this->createPromotionAssessment();

        $this->service->delete($assessment);

        $this->assertDatabaseMissing(
            'promotion_assessments',
            [
                'id' => $assessment->id,
            ],
        );
    }
}
