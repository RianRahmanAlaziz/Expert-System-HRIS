<?php

namespace Tests\Unit\Consultation;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\Position;
use App\Models\User;
use App\Services\Consultation\ExpertConsultationService;
use App\Services\Employee\EmployeeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpertConsultationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExpertConsultationService $expertConsultationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->expertConsultationService = app(
            ExpertConsultationService::class
        );
    }

    private function createEmployee(): Employee
    {
        $suffix = uniqid();

        $department = Department::query()->create([
            'code' => 'HR-' . $suffix,
            'name' => 'Human Resources ' . $suffix,
            'description' => 'Human Resources Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = Position::query()->create([
            'code' => 'HR-STAFF-' . $suffix,
            'name' => 'HR Staff ' . $suffix,
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        return app(EmployeeService::class)->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-' . $suffix,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => now()->subYears(30),
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => now()->subYears(2),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee joined the company.',
        ]);
    }

    private function createConsultation(
        Employee $employee,
        User $user,
        string $consultationType = 'promotion',
    ): ExpertConsultation {
        return ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => $consultationType,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_it_can_find_expert_consultation(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $consultation = $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $result = $this->expertConsultationService->findById(
            $consultation->id
        );

        $this->assertTrue(
            $result->is($consultation)
        );
    }

    public function test_it_throws_exception_when_expert_consultation_not_found(): void
    {
        $this->expectException(
            ModelNotFoundException::class
        );

        $this->expertConsultationService->findById(
            999999
        );
    }

    public function test_it_can_paginate_expert_consultations(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $result = $this->expertConsultationService->paginate(
            perPage: 15
        );

        $this->assertSame(
            3,
            $result->total()
        );
    }

    public function test_it_can_filter_consultations_by_employee(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();
        $otherEmployee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->createConsultation(
            employee: $otherEmployee,
            user: $user,
        );

        $result = $this->expertConsultationService->paginate(
            employeeId: $employee->id
        );

        $this->assertSame(
            1,
            $result->total()
        );

        $this->assertSame(
            $employee->id,
            $result->items()[0]->employee_id
        );
    }

    public function test_it_can_filter_consultations_by_type(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
            consultationType: 'promotion',
        );

        $this->createConsultation(
            employee: $employee,
            user: $user,
            consultationType: 'training',
        );

        $result = $this->expertConsultationService->paginate(
            consultationType: 'promotion'
        );

        $this->assertSame(
            1,
            $result->total()
        );

        $this->assertSame(
            'promotion',
            $result->items()[0]->consultation_type
        );
    }

    public function test_it_can_create_expert_consultation(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $result = $this->expertConsultationService->create(
            user: $user,
            data: [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]
        );

        $this->assertInstanceOf(
            ExpertConsultation::class,
            $result
        );

        $this->assertSame(
            $employee->id,
            $result->employee_id
        );

        $this->assertSame(
            $user->id,
            $result->user_id
        );

        $this->assertSame(
            'promotion',
            $result->consultation_type
        );

        $this->assertSame(
            'completed',
            $result->status
        );

        $this->assertNotNull(
            $result->started_at
        );

        $this->assertNotNull(
            $result->completed_at
        );
    }

    public function test_it_creates_consultation_result(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $consultation = $this->expertConsultationService->create(
            user: $user,
            data: [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]
        );

        $this->assertNotNull(
            $consultation->result
        );

        $this->assertSame(
            $consultation->id,
            $consultation->result->expert_consultation_id
        );

        $this->assertDatabaseHas(
            'consultation_results',
            [
                'expert_consultation_id' => $consultation->id,
            ]
        );
    }

    public function test_it_stores_input_snapshot(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $consultation = $this->expertConsultationService->create(
            user: $user,
            data: [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]
        );

        $this->assertIsArray(
            $consultation->result->input_snapshot
        );

        $this->assertArrayHasKey(
            'employee',
            $consultation->result->input_snapshot
        );

        $this->assertSame(
            $employee->id,
            $consultation->result->input_snapshot['employee']['id']
        );
    }

    public function test_it_returns_not_recommended_when_no_rule_matches(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $consultation = $this->expertConsultationService->create(
            user: $user,
            data: [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]
        );

        $this->assertSame(
            'Not Recommended',
            $consultation->result->recommendation
        );

        $this->assertSame(
            [],
            $consultation->result->matched_rules
        );
    }

    public function test_it_stores_matched_rules_as_array(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $consultation = $this->expertConsultationService->create(
            user: $user,
            data: [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]
        );

        $this->assertIsArray(
            $consultation->result->matched_rules
        );
    }

    public function test_it_stores_suggested_actions_as_array(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $consultation = $this->expertConsultationService->create(
            user: $user,
            data: [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]
        );

        $this->assertIsArray(
            $consultation->result->suggested_actions
        );
    }

    public function test_it_logs_activity_when_creating_expert_consultation(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $consultation = $this->expertConsultationService->create(
            user: $user,
            data: [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]
        );

        $log = ActivityLog::query()
            ->where('action', 'create')
            ->where('module', 'expert_consultation')
            ->where('target_id', $consultation->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $consultation->getMorphClass(),
            $log->target_type,
        );

        $this->assertSame(
            $employee->id,
            $log->new_values['employee_id'],
        );

        $this->assertSame(
            $user->id,
            $log->new_values['user_id'],
        );

        $this->assertSame(
            'promotion',
            $log->new_values['consultation_type'],
        );

        $this->assertSame(
            'completed',
            $log->new_values['status'],
        );

        $this->assertArrayHasKey(
            'recommendation',
            $log->new_values,
        );

        $this->assertArrayHasKey(
            'score',
            $log->new_values,
        );

        $this->assertArrayHasKey(
            'confidence',
            $log->new_values,
        );
    }
}
