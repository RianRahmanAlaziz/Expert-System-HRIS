<?php

namespace Tests\Feature\Promotion;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\PromotionAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromotionAssessmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPermission(string $permission): void
    {
        Permission::findOrCreate($permission, 'web');
    }

    private function createUserWithPermission(string $permission): User
    {
        $this->createPermission($permission);

        $user = User::factory()->create();
        $role = Role::findOrCreate('test-role-' . uniqid(), 'web');
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        return $user;
    }

    private function createDepartment(array $overrides = []): Department
    {
        return Department::query()->create(array_merge([
            'code' => 'DEP-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Technology',
            'description' => 'Technology Department',
            'status' => 'active',
            'is_active' => true,
        ], $overrides));
    }

    private function createPosition(array $overrides = []): Position
    {
        return Position::query()->create(array_merge([
            'code' => 'POS-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Software Engineer',
            'description' => 'Software Engineer Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ], $overrides));
    }

    private function createEmployee(
        array $overrides = [],
        ?Department $department = null,
        ?Position $position = null,
    ): Employee {
        $department ??= $this->createDepartment();
        $position ??= $this->createPosition();

        return Employee::query()->create(array_merge([
            'user_id' => null,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => null,
            'employee_number' => 'EMP-' . strtoupper(substr(uniqid(), -6)),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '08123456789',
            'address' => 'Jakarta',
            'join_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ], $overrides));
    }

    private function createAssessment(User $assessor, array $overrides = []): PromotionAssessment
    {
        $employee = $overrides['employee'] ?? $this->createEmployee();
        $currentPosition = $overrides['current_position'] ?? $this->createPosition([
            'name' => 'Junior Engineer',
        ]);
        $targetPosition = $overrides['target_position'] ?? $this->createPosition([
            'name' => 'Senior Engineer',
        ]);

        unset($overrides['employee'], $overrides['current_position'], $overrides['target_position']);

        return PromotionAssessment::query()->create(array_merge([
            'employee_id' => $employee->id,
            'current_position_id' => $currentPosition->id,
            'target_position_id' => $targetPosition->id,
            'assessed_by' => $assessor->id,
            'assessment_date' => '2026-01-15',
            'status' => 'draft',
            'overall_score' => 80.00,
            'recommendation' => 'promote',
            'notes' => 'Assessment notes',
        ], $overrides));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/promotion-assessments')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/promotion-assessments')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_assessments(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.view');
        $this->createAssessment($user);
        $this->createAssessment($user);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/promotion-assessments?per_page=1');

        $response->assertOk()
            ->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_index_can_filter_by_employee(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.view');
        $employee = $this->createEmployee();
        $this->createAssessment($user, ['employee' => $employee]);
        $this->createAssessment($user);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/promotion-assessments?employee_id={$employee->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $employee->id);
    }

    public function test_index_can_filter_by_positions_assessor_and_status(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.view');
        $employee = $this->createEmployee();
        $current = $this->createPosition(['name' => 'Current Position']);
        $target = $this->createPosition(['name' => 'Target Position']);

        $this->createAssessment($user, [
            'employee' => $employee,
            'current_position' => $current,
            'target_position' => $target,
            'status' => 'completed',
        ]);
        $this->createAssessment($user, ['status' => 'draft']);

        $query = http_build_query([
            'current_position_id' => $current->id,
            'target_position_id' => $target->id,
            'assessed_by' => $user->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/promotion-assessments?{$query}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    public function test_show_returns_assessment_with_relationships(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.view');
        $assessment = $this->createAssessment($user);

        $this->actingAs($user)
            ->getJson("/api/v1/promotion-assessments/{$assessment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assessment->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'employee',
                    'current_position',
                    'target_position',
                    'assessed_by_user',
                    'items',
                    'assessment_date',
                    'status',
                    'overall_score',
                    'recommendation',
                    'notes',
                ],
            ]);
    }

    public function test_show_returns_not_found_for_missing_assessment(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.view');

        $this->actingAs($user)
            ->getJson('/api/v1/promotion-assessments/999999')
            ->assertNotFound();
    }

    public function test_store_creates_assessment_and_sets_authenticated_assessor(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.create');
        $employee = $this->createEmployee();
        $current = $this->createPosition();
        $target = $this->createPosition();

        $payload = [
            'employee_id' => $employee->id,
            'current_position_id' => $current->id,
            'target_position_id' => $target->id,
            'assessment_date' => '2026-02-01',
            'status' => 'draft',
            'overall_score' => 85,
            'recommendation' => 'promote',
            'notes' => 'Ready for promotion',
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessments', $payload)
            ->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.assessed_by', $user->id);

        $this->assertDatabaseHas('promotion_assessments', [
            'employee_id' => $employee->id,
            'assessed_by' => $user->id,
            'status' => 'draft',
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessments', [])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.create');

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_id',
                'current_position_id',
                'target_position_id',
                'assessment_date',
                'status',
            ]);
    }

    public function test_store_validates_foreign_keys_and_score(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.create');

        $payload = [
            'employee_id' => 999999,
            'current_position_id' => 999999,
            'target_position_id' => 999999,
            'assessment_date' => 'invalid-date',
            'status' => str_repeat('x', 31),
            'overall_score' => 101,
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_id',
                'current_position_id',
                'target_position_id',
                'assessment_date',
                'status',
                'overall_score',
            ]);
    }

    public function test_update_requires_permission(): void
    {
        $owner = $this->createUserWithPermission('promotion_assessment.view');
        $user = User::factory()->create();
        $assessment = $this->createAssessment($owner);

        $this->actingAs($user)
            ->putJson("/api/v1/promotion-assessments/{$assessment->id}", [
                'status' => 'completed',
            ])
            ->assertForbidden();
    }

    public function test_update_can_partially_update_assessment(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.update');
        $assessment = $this->createAssessment($user);

        $this->actingAs($user)
            ->putJson("/api/v1/promotion-assessments/{$assessment->id}", [
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.notes', 'Assessment notes');

        $this->assertDatabaseHas('promotion_assessments', [
            'id' => $assessment->id,
            'status' => 'completed',
        ]);
    }

    public function test_update_returns_not_found_for_missing_assessment(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.update');

        $this->actingAs($user)
            ->putJson('/api/v1/promotion-assessments/999999', [
                'status' => 'completed',
            ])
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $owner = $this->createUserWithPermission('promotion_assessment.view');
        $user = User::factory()->create();
        $assessment = $this->createAssessment($owner);

        $this->actingAs($user)
            ->deleteJson("/api/v1/promotion-assessments/{$assessment->id}")
            ->assertForbidden();
    }

    public function test_destroy_deletes_assessment_and_returns_null_data(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.delete');
        $assessment = $this->createAssessment($user);

        $this->actingAs($user)
            ->deleteJson("/api/v1/promotion-assessments/{$assessment->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('promotion_assessments', [
            'id' => $assessment->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_assessment(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/promotion-assessments/999999')
            ->assertNotFound();
    }
}
