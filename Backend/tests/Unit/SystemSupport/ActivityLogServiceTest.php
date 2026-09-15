<?php

namespace Tests\Unit\SystemSupport;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class ActivityLogServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActivityLogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ActivityLogService::class);
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

    public function test_it_can_create_activity_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $log = $this->service->log(
            action: 'created',
            module: 'employee',
        );

        $this->assertInstanceOf(ActivityLog::class, $log);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'action' => 'created',
            'module' => 'employee',
        ]);
    }

    public function test_it_can_create_audit_trail_for_target_model(): void
    {
        $user = User::factory()->create();
        $employee = $this->createEmployee();

        $this->actingAs($user);

        $log = $this->service->log(
            action: 'updated',
            module: 'employee',
            target: $employee,
            oldValues: [
                'status' => 'active',
            ],
            newValues: [
                'status' => 'inactive',
            ],
        );

        $this->assertEquals(
            $employee->getMorphClass(),
            $log->target_type,
        );

        $this->assertEquals($employee->id, $log->target_id);

        $this->assertEquals(
            ['status' => 'active'],
            $log->old_values,
        );

        $this->assertEquals(
            ['status' => 'inactive'],
            $log->new_values,
        );
    }

    public function test_it_uses_explicit_user_id(): void
    {
        $authenticatedUser = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($authenticatedUser);

        $log = $this->service->log(
            action: 'created',
            module: 'employee',
            userId: $targetUser->id,
        );

        $this->assertEquals($targetUser->id, $log->user_id);
    }

    public function test_it_can_create_system_activity_without_authenticated_user(): void
    {
        $log = $this->service->log(
            action: 'scheduled',
            module: 'system',
        );

        $this->assertNull($log->user_id);

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'user_id' => null,
            'action' => 'scheduled',
            'module' => 'system',
        ]);
    }

    public function test_it_can_paginate_activity_logs(): void
    {
        $user = User::factory()->create();

        ActivityLog::factory()->count(20)->create([
            'user_id' => $user->id,
            'module' => 'employee',
        ]);

        $result = $this->service->paginate(
            perPage: 10,
            module: 'employee',
        );

        $this->assertCount(10, $result->items());
        $this->assertEquals(20, $result->total());
        $this->assertEquals(10, $result->perPage());
    }

    public function test_it_can_filter_activity_logs_by_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ActivityLog::factory()->create([
            'user_id' => $user->id,
            'module' => 'employee',
        ]);

        ActivityLog::factory()->create([
            'user_id' => $otherUser->id,
            'module' => 'employee',
        ]);

        $result = $this->service->paginate(
            userId: $user->id,
        );

        $this->assertCount(1, $result->items());
        $this->assertEquals(
            $user->id,
            $result->items()[0]->user_id,
        );
    }

    public function test_it_can_filter_activity_logs_by_search(): void
    {
        ActivityLog::factory()->create([
            'action' => 'employee_created',
            'module' => 'employee',
        ]);

        ActivityLog::factory()->create([
            'action' => 'leave_approved',
            'module' => 'leave',
        ]);

        $result = $this->service->paginate(
            search: 'employee',
        );

        $this->assertCount(1, $result->items());
        $this->assertEquals(
            'employee_created',
            $result->items()[0]->action,
        );
    }

    public function test_it_can_find_activity_log_by_id(): void
    {
        $log = ActivityLog::factory()->create();

        $result = $this->service->findById($log->id);

        $this->assertEquals($log->id, $result->id);
        $this->assertTrue($result->relationLoaded('user'));
    }
}
