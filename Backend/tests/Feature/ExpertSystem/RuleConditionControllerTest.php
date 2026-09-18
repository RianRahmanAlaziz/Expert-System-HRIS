<?php

namespace Tests\Feature\ExpertSystem;

use App\Models\ExpertRule;
use App\Models\Knowledge;
use App\Models\KnowledgeCategory;
use App\Models\RuleCondition;
use App\Models\User;
use App\Services\ExpertSystem\ExpertParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RuleConditionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');

        $user = User::factory()->create();
        $role = Role::findOrCreate('test-role-' . uniqid(), 'web');
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        return $user;
    }

    private function createKnowledge(): Knowledge
    {
        $category = KnowledgeCategory::query()->create([
            'code' => 'KC-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Competency Knowledge',
            'description' => 'Knowledge category',
            'status' => 'active',
        ]);

        return Knowledge::query()->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Promotion Knowledge',
            'description' => 'Promotion knowledge',
            'version' => 1,
            'status' => 'active',
        ]);
    }

    private function createExpertRule(array $overrides = []): ExpertRule
    {
        $knowledge = $overrides['knowledge'] ?? $this->createKnowledge();
        unset($overrides['knowledge']);

        return ExpertRule::query()->create(array_merge([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Promotion Rule',
            'description' => 'Promotion rule',
            'priority' => 1,
            'status' => 'active',
        ], $overrides));
    }

    private function createCondition(
        ExpertRule $rule,
        array $overrides = [],
    ): RuleCondition {
        return RuleCondition::query()->create(array_merge([
            'expert_rule_id' => $rule->id,
            'parameter' => ExpertParameter::PERFORMANCE,
            'operator' => '>=',
            'value' => '80',
            'logical_operator' => 'AND',
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/rule-conditions')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/rule-conditions')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_conditions(): void
    {
        $user = $this->createUserWithPermission('rule_condition.view');
        $rule = $this->createExpertRule();

        $this->createCondition($rule);
        $this->createCondition($rule, [
            'parameter' => 'competency_score',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/rule-conditions?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_index_can_filter_by_expert_rule(): void
    {
        $user = $this->createUserWithPermission('rule_condition.view');
        $rule = $this->createExpertRule();
        $otherRule = $this->createExpertRule();

        $this->createCondition($rule);
        $this->createCondition($otherRule, [
            'parameter' => 'attendance_score',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/rule-conditions?expert_rule_id={$rule->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.expert_rule.id', $rule->id);
    }

    public function test_show_returns_condition_with_rule(): void
    {
        $user = $this->createUserWithPermission('rule_condition.view');
        $rule = $this->createExpertRule();
        $condition = $this->createCondition($rule);

        $this->actingAs($user)
            ->getJson("/api/v1/rule-conditions/{$condition->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $condition->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'expert_rule',
                    'parameter',
                    'operator',
                    'value',
                    'logical_operator',
                    'sort_order',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_returns_not_found_for_missing_condition(): void
    {
        $user = $this->createUserWithPermission('rule_condition.view');

        $this->actingAs($user)
            ->getJson('/api/v1/rule-conditions/999999')
            ->assertNotFound();
    }

    public function test_store_creates_condition(): void
    {
        $user = $this->createUserWithPermission('rule_condition.create');
        $rule = $this->createExpertRule();

        $this->actingAs($user)
            ->postJson('/api/v1/rule-conditions', [
                'expert_rule_id' => $rule->id,
                'parameter' => ExpertParameter::PERFORMANCE,
                'operator' => '>=',
                'value' => '80',
                'logical_operator' => 'AND',
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.expert_rule.id', $rule->id)
            ->assertJsonPath('data.parameter',  ExpertParameter::PERFORMANCE);

        $this->assertDatabaseHas('rule_conditions', [
            'expert_rule_id' => $rule->id,
            'parameter' => ExpertParameter::PERFORMANCE,
            'operator' => '>=',
        ]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/rule-conditions', [])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->createUserWithPermission('rule_condition.create');

        $this->actingAs($user)
            ->postJson('/api/v1/rule-conditions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'expert_rule_id',
                'parameter',
                'operator',
                'value',
            ]);
    }

    public function test_store_validates_foreign_key_and_field_lengths(): void
    {
        $user = $this->createUserWithPermission('rule_condition.create');

        $this->actingAs($user)
            ->postJson('/api/v1/rule-conditions', [
                'expert_rule_id' => 999999,
                'parameter' => str_repeat('x', 101),
                'operator' => 'INVALID',
                'value' => str_repeat('x', 256),
                'logical_operator' => 'INVALID',
                'sort_order' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'expert_rule_id',
                'parameter',
                'operator',
                'value',
                'logical_operator',
                'sort_order',
            ]);
    }

    public function test_update_requires_permission(): void
    {
        $user = User::factory()->create();
        $rule = $this->createExpertRule();
        $condition = $this->createCondition($rule);

        $this->actingAs($user)
            ->putJson("/api/v1/rule-conditions/{$condition->id}", [
                'value' => '90',
            ])
            ->assertForbidden();
    }

    public function test_update_can_partially_update_condition(): void
    {
        $user = $this->createUserWithPermission('rule_condition.update');
        $rule = $this->createExpertRule();
        $condition = $this->createCondition($rule);

        $this->actingAs($user)
            ->putJson("/api/v1/rule-conditions/{$condition->id}", [
                'value' => '90',
            ])
            ->assertOk()
            ->assertJsonPath('data.value', '90')
            ->assertJsonPath('data.expert_rule.id', $rule->id);
    }

    public function test_update_can_change_expert_rule(): void
    {
        $user = $this->createUserWithPermission('rule_condition.update');
        $rule = $this->createExpertRule();
        $newRule = $this->createExpertRule();
        $condition = $this->createCondition($rule);

        $this->actingAs($user)
            ->putJson("/api/v1/rule-conditions/{$condition->id}", [
                'expert_rule_id' => $newRule->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.expert_rule.id', $newRule->id);
    }

    public function test_update_returns_not_found_for_missing_condition(): void
    {
        $user = $this->createUserWithPermission('rule_condition.update');

        $this->actingAs($user)
            ->putJson('/api/v1/rule-conditions/999999', [
                'value' => '90',
            ])
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $user = $this->createUserWithPermission('rule_condition.view');
        $rule = $this->createExpertRule();
        $condition = $this->createCondition($rule);

        $this->actingAs($user)
            ->deleteJson("/api/v1/rule-conditions/{$condition->id}")
            ->assertForbidden();
    }

    public function test_destroy_deletes_condition_and_returns_null_data(): void
    {
        $user = $this->createUserWithPermission('rule_condition.delete');
        $rule = $this->createExpertRule();
        $condition = $this->createCondition($rule);

        $this->actingAs($user)
            ->deleteJson("/api/v1/rule-conditions/{$condition->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('rule_conditions', [
            'id' => $condition->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_condition(): void
    {
        $user = $this->createUserWithPermission('rule_condition.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/rule-conditions/999999')
            ->assertNotFound();
    }
}
