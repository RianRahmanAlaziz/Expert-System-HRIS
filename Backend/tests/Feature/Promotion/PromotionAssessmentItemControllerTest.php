<?php

namespace Tests\Feature\Promotion;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\PromotionAssessment;
use App\Models\PromotionAssessmentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromotionAssessmentItemControllerTest extends TestCase
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
        $currentPosition = $overrides['current_position'] ?? $this->createPosition();
        $targetPosition = $overrides['target_position'] ?? $this->createPosition();

        unset($overrides['employee'], $overrides['current_position'], $overrides['target_position']);

        return PromotionAssessment::query()->create(array_merge([
            'employee_id' => $employee->id,
            'current_position_id' => $currentPosition->id,
            'target_position_id' => $targetPosition->id,
            'assessed_by' => $assessor->id,
            'assessment_date' => '2026-01-15',
            'status' => 'draft',
            'overall_score' => 80,
            'recommendation' => 'promote',
            'notes' => 'Assessment notes',
        ], $overrides));
    }

    private function createItem(PromotionAssessment $assessment, array $overrides = []): PromotionAssessmentItem
    {
        return PromotionAssessmentItem::query()->create(array_merge([
            'promotion_assessment_id' => $assessment->id,
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-001',
            'criterion_name' => 'Technical Competency',
            'score' => 85,
            'weight' => 30,
            'is_passed' => true,
            'notes' => 'Good result',
        ], $overrides));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/promotion-assessment-items')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/promotion-assessment-items')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_items(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.view');
        $assessment = $this->createAssessment($user);
        $this->createItem($assessment);
        $this->createItem($assessment, [
            'criterion_code' => 'PERF-001',
            'criterion_name' => 'Performance',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/promotion-assessment-items?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_index_can_filter_by_assessment_criterion_type_and_passed(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.view');
        $assessment = $this->createAssessment($user);
        $otherAssessment = $this->createAssessment($user);

        $this->createItem($assessment, [
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-001',
            'is_passed' => true,
        ]);
        $this->createItem($assessment, [
            'criterion_type' => 'performance',
            'criterion_code' => 'PERF-001',
            'is_passed' => false,
        ]);
        $this->createItem($otherAssessment, [
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-002',
            'is_passed' => true,
        ]);

        $query = http_build_query([
            'promotion_assessment_id' => $assessment->id,
            'criterion_type' => 'competency',
            'is_passed' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/promotion-assessment-items?{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.criterion_code', 'COMP-001');
    }

    public function test_show_returns_item(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.view');
        $assessment = $this->createAssessment($user);
        $item = $this->createItem($assessment);

        $this->actingAs($user)
            ->getJson("/api/v1/promotion-assessment-items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'promotion_assessment_id',
                    'criterion_type',
                    'criterion_code',
                    'criterion_name',
                    'score',
                    'weight',
                    'is_passed',
                    'notes',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_returns_not_found_for_missing_item(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.view');

        $this->actingAs($user)
            ->getJson('/api/v1/promotion-assessment-items/999999')
            ->assertNotFound();
    }

    public function test_store_creates_item(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.create');
        $assessment = $this->createAssessment($user);

        $payload = [
            'promotion_assessment_id' => $assessment->id,
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-001',
            'criterion_name' => 'Leadership',
            'score' => 90,
            'weight' => 25,
            'is_passed' => true,
            'notes' => 'Excellent',
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessment-items', $payload)
            ->assertCreated()
            ->assertJsonPath('data.promotion_assessment_id', $assessment->id)
            ->assertJsonPath('data.criterion_code', 'COMP-001');
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessment-items', [])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.create');

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessment-items', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'promotion_assessment_id',
                'criterion_type',
                'criterion_code',
                'criterion_name',
                'weight',
            ]);
    }

    public function test_store_validates_foreign_keys_and_numeric_bounds(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.create');

        $payload = [
            'promotion_assessment_id' => 999999,
            'criterion_type' => str_repeat('x', 51),
            'criterion_code' => str_repeat('x', 101),
            'criterion_name' => str_repeat('x', 151),
            'score' => 101,
            'weight' => 101,
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessment-items', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'promotion_assessment_id',
                'criterion_type',
                'criterion_code',
                'criterion_name',
                'score',
                'weight',
            ]);
    }

    public function test_store_rejects_duplicate_criterion_in_same_assessment(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.create');
        $assessment = $this->createAssessment($user);
        $this->createItem($assessment);

        $payload = [
            'promotion_assessment_id' => $assessment->id,
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-001',
            'criterion_name' => 'Another Name',
            'score' => 90,
            'weight' => 20,
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessment-items', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['criterion_code']);
    }

    public function test_same_criterion_can_exist_in_different_assessments(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.create');
        $assessmentOne = $this->createAssessment($user);
        $assessmentTwo = $this->createAssessment($user);

        $this->createItem($assessmentOne);

        $this->actingAs($user)
            ->postJson('/api/v1/promotion-assessment-items', [
                'promotion_assessment_id' => $assessmentTwo->id,
                'criterion_type' => 'competency',
                'criterion_code' => 'COMP-001',
                'criterion_name' => 'Technical Competency',
                'score' => 85,
                'weight' => 30,
            ])
            ->assertCreated();
    }

    public function test_update_requires_permission(): void
    {
        $owner = $this->createUserWithPermission('promotion_assessment_item.view');
        $user = User::factory()->create();
        $assessment = $this->createAssessment($owner);
        $item = $this->createItem($assessment);

        $this->actingAs($user)
            ->putJson("/api/v1/promotion-assessment-items/{$item->id}", [
                'score' => 95,
            ])
            ->assertForbidden();
    }

    public function test_update_can_partially_update_item(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.update');
        $assessment = $this->createAssessment($user);
        $item = $this->createItem($assessment);

        $this->actingAs($user)
            ->putJson("/api/v1/promotion-assessment-items/{$item->id}", [
                'score' => 95,
            ])
            ->assertOk()
            ->assertJsonPath('data.score', '95.00')
            ->assertJsonPath('data.weight', '30.00');
    }

    public function test_update_rejects_duplicate_criterion(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.update');
        $assessment = $this->createAssessment($user);
        $first = $this->createItem($assessment);
        $second = $this->createItem($assessment, [
            'criterion_code' => 'PERF-001',
            'criterion_name' => 'Performance',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/promotion-assessment-items/{$second->id}", [
                'criterion_code' => $first->criterion_code,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['criterion_code']);
    }

    public function test_by_assessment_returns_items_in_expected_order(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.view');
        $assessment = $this->createAssessment($user);

        $this->createItem($assessment, [
            'criterion_type' => 'performance',
            'criterion_code' => 'PERF-002',
        ]);
        $this->createItem($assessment, [
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-002',
        ]);
        $this->createItem($assessment, [
            'criterion_type' => 'competency',
            'criterion_code' => 'COMP-001',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/promotion-assessments/{$assessment->id}/items")
            ->assertOk()
            ->assertJsonPath('data.0.criterion_code', 'COMP-001')
            ->assertJsonPath('data.1.criterion_code', 'COMP-002')
            ->assertJsonPath('data.2.criterion_code', 'PERF-002');
    }

    public function test_by_assessment_returns_not_found_for_missing_assessment(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.view');

        $this->actingAs($user)
            ->getJson('/api/v1/promotion-assessments/999999/items')
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $owner = $this->createUserWithPermission('promotion_assessment_item.view');
        $user = User::factory()->create();
        $assessment = $this->createAssessment($owner);
        $item = $this->createItem($assessment);

        $this->actingAs($user)
            ->deleteJson("/api/v1/promotion-assessment-items/{$item->id}")
            ->assertForbidden();
    }

    public function test_destroy_deletes_item_and_returns_null_data(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.delete');
        $assessment = $this->createAssessment($user);
        $item = $this->createItem($assessment);

        $this->actingAs($user)
            ->deleteJson("/api/v1/promotion-assessment-items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('promotion_assessment_items', [
            'id' => $item->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_item(): void
    {
        $user = $this->createUserWithPermission('promotion_assessment_item.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/promotion-assessment-items/999999')
            ->assertNotFound();
    }
}
