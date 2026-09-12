<?php

namespace Tests\Unit\Recommendation;

use App\Models\ConsultationResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\Position;
use App\Models\Recommendation;
use App\Models\User;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RecommendationService::class);
    }

    private function createDepartment(
        string $code = 'HR',
    ): Department {
        return Department::query()->create([
            'code' => $code,
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(
        string $code = 'STAFF',
    ): Position {
        return Position::query()->create([
            'code' => $code,
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        string $employeeNumber = 'EMP-001',
        ?Department $department = null,
        ?Position $position = null,
    ): Employee {
        $department ??= $this->createDepartment();
        $position ??= $this->createPosition();

        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => $employeeNumber,
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

    public function test_it_can_create_recommendation_from_consultation(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();

        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => 'promotion',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        ConsultationResult::query()->create([
            'expert_consultation_id' => $consultation->id,
            'recommendation' => 'Recommended',
            'score' => 85,
            'confidence' => 90,
            'reason' => 'Employee memenuhi kriteria promotion.',
            'input_snapshot' => [
                'performance' => 90,
                'competency' => 85,
                'attendance' => 95,
                'experience' => 4,
            ],
            'matched_rules' => [],
            'suggested_actions' => [
                [
                    'action_type' => 'recommendation',
                    'action_value' => 'promotion',
                    'description' => 'Direkomendasikan untuk promosi.',
                ],
            ],
        ]);

        $recommendation = $this->service->createFromConsultation(
            consultation: $consultation,
            data: [
                'type' => 'promotion',
                'title' => 'Rekomendasi Promosi',
                'description' => 'Employee direkomendasikan untuk promosi.',
                'priority' => 'high',
            ],
        );

        $this->assertInstanceOf(
            Recommendation::class,
            $recommendation,
        );

        $this->assertDatabaseHas('recommendations', [
            'id' => $recommendation->id,
            'employee_id' => $employee->id,
            'expert_consultation_id' => $consultation->id,
            'type' => 'promotion',
            'title' => 'Rekomendasi Promosi',
            'priority' => 'high',
            'status' => 'pending',
        ]);
    }

    public function test_it_uses_consultation_result_reason_when_description_is_not_provided(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();

        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => 'training',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        ConsultationResult::query()->create([
            'expert_consultation_id' => $consultation->id,
            'recommendation' => 'Consider',
            'score' => 70,
            'confidence' => 80,
            'reason' => 'Employee membutuhkan peningkatan kompetensi.',
            'input_snapshot' => [],
            'matched_rules' => [],
            'suggested_actions' => [],
        ]);

        $recommendation = $this->service->createFromConsultation(
            consultation: $consultation,
            data: [
                'type' => 'training',
                'title' => 'Rekomendasi Training',
                'priority' => 'medium',
            ],
        );

        $this->assertSame(
            'Employee membutuhkan peningkatan kompetensi.',
            $recommendation->description,
        );
    }

    public function test_it_cannot_create_recommendation_without_consultation_result(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();

        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => 'career',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->service->createFromConsultation(
            consultation: $consultation,
            data: [
                'type' => 'career',
                'title' => 'Career Recommendation',
            ],
        );
    }

    public function test_it_can_update_recommendation_status_and_create_history(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();

        $recommendation = Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'title' => 'Rekomendasi Promosi',
            'description' => 'Employee direkomendasikan untuk promosi.',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $updated = $this->service->updateStatus(
            recommendation: $recommendation,
            status: 'approved',
            notes: 'Disetujui oleh HR.',
            userId: $user->id,
        );

        $this->assertSame('approved', $updated->status);

        $this->assertDatabaseHas('recommendations', [
            'id' => $recommendation->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('recommendation_histories', [
            'recommendation_id' => $recommendation->id,
            'user_id' => $user->id,
            'old_status' => 'pending',
            'new_status' => 'approved',
            'notes' => 'Disetujui oleh HR.',
        ]);
    }

    public function test_it_does_not_create_duplicate_history_when_status_is_unchanged(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();

        $recommendation = Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'employee_risk',
            'title' => 'Employee Risk',
            'description' => 'Perlu perhatian HR.',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $this->service->updateStatus(
            recommendation: $recommendation,
            status: 'pending',
            notes: 'Status tetap pending.',
            userId: $user->id,
        );

        $this->assertDatabaseCount(
            'recommendation_histories',
            0,
        );
    }

    public function test_it_can_find_recommendation_by_id(): void
    {
        $employee = $this->createEmployee();

        $recommendation = Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'performance_improvement',
            'title' => 'Performance Improvement',
            'description' => 'Perlu peningkatan performance.',
            'priority' => 'medium',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $result = $this->service->findById(
            id: $recommendation->id,
        );

        $this->assertTrue(
            $result->is($recommendation),
        );

        $this->assertTrue(
            $result->relationLoaded('employee'),
        );

        $this->assertTrue(
            $result->relationLoaded('histories'),
        );
    }

    public function test_it_can_paginate_recommendations(): void
    {
        $employee = $this->createEmployee();

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'title' => 'Promotion Recommendation',
            'description' => 'Promotion.',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'training',
            'title' => 'Training Recommendation',
            'description' => 'Training.',
            'priority' => 'medium',
            'status' => 'approved',
            'recommended_at' => now(),
        ]);

        $result = $this->service->paginate(
            perPage: 15,
        );

        $this->assertSame(2, $result->total());
    }

    public function test_it_can_filter_recommendations_by_type(): void
    {
        $employee = $this->createEmployee();

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'title' => 'Promotion Recommendation',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'training',
            'title' => 'Training Recommendation',
            'priority' => 'medium',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $result = $this->service->paginate(
            perPage: 15,
            type: 'promotion',
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'promotion',
            $result->items()[0]->type,
        );
    }

    public function test_it_can_filter_recommendations_by_status(): void
    {
        $employee = $this->createEmployee();

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'title' => 'Promotion Recommendation',
            'priority' => 'high',
            'status' => 'approved',
            'recommended_at' => now(),
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'career',
            'title' => 'Career Recommendation',
            'priority' => 'medium',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $result = $this->service->paginate(
            perPage: 15,
            status: 'approved',
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'approved',
            $result->items()[0]->status,
        );
    }
}
