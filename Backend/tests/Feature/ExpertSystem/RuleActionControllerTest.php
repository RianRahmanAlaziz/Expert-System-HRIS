<?php

namespace Tests\Feature\ExpertSystem;

use App\Models\ExpertRule;
use App\Models\Knowledge;
use App\Models\KnowledgeCategory;
use App\Models\RuleAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RuleActionControllerTest extends TestCase
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

    private function createAction(
        ExpertRule $rule,
        array $overrides = [],
    ): RuleAction {
        return RuleAction::query()->create(array_merge([
            'expert_rule_id' => $rule->id,
            'action_type' => 'recommendation',
            'action_value' => 'promote',
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/rule-actions')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/rule-actions')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_actions(): void
    {
        $user = $this->createUserWithPermission('rule_action.view');
        $rule = $this->createExpertRule();

        $this->createAction($rule);
        $this->createAction($rule, [
            'action_type' => 'notification',
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/rule-actions?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_index_can_filter_by_expert_rule(): void
    {
        $user = $this->createUserWithPermission('rule_action.view');
        $rule = $this->createExpertRule();
        $otherRule = $this->createExpertRule();

        $this->createAction($rule);
        $this->createAction($otherRule, [
            'action_type' => 'notification',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/rule-actions?expert_rule_id={$rule->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.expert_rule.id', $rule->id);
    }

    public function test_show_returns_action_with_rule(): void
    {
        $user = $this->createUserWithPermission('rule_action.view');
        $rule = $this->createExpertRule();
        $action = $this->createAction($rule);

        $this->actingAs($user)
            ->getJson("/api/v1/rule-actions/{$action->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $action->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'expert_rule',
                    'action_type',
                    'action_value',
                ],
            ]);
    }

    public function test_show_returns_not_found_for_missing_action(): void
    {
        $user = $this->createUserWithPermission('rule_action.view');

        $this->actingAs($user)
            ->getJson('/api/v1/rule-actions/999999')
            ->assertNotFound();
    }

    public function test_store_creates_action(): void
    {
        $user = $this->createUserWithPermission('rule_action.create');
        $rule = $this->createExpertRule();

        $this->actingAs($user)
            ->postJson('/api/v1/rule-actions', [
                'expert_rule_id' => $rule->id,
                'action_type' => 'recommendation',
                'action_value' => 'promote',
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.expert_rule.id', $rule->id)
            ->assertJsonPath('data.action_type', 'recommendation');

        $this->assertDatabaseHas('rule_actions', [
            'expert_rule_id' => $rule->id,
            'action_type' => 'recommendation',
            'action_value' => 'promote',
        ]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/rule-actions', [])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->createUserWithPermission('rule_action.create');

        $this->actingAs($user)
            ->postJson('/api/v1/rule-actions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'expert_rule_id',
                'action_type',
                'action_value',
            ]);
    }

    public function test_store_validates_foreign_key_and_field_lengths(): void
    {
        $user = $this->createUserWithPermission('rule_action.create');

        $this->actingAs($user)
            ->postJson('/api/v1/rule-actions', [
                'expert_rule_id' => 999999,
                'action_type' => str_repeat('x', 51),
                'action_value' => str_repeat('x', 256),
                'sort_order' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'expert_rule_id',
                'action_type',
                'action_value',
            ]);
    }

    public function test_update_requires_permission(): void
    {
        $user = User::factory()->create();
        $owner = $this->createUserWithPermission('rule_action.view');
        $rule = $this->createExpertRule();
        $action = $this->createAction($rule);

        $this->actingAs($user)
            ->putJson("/api/v1/rule-actions/{$action->id}", [
                'action_value' => 'reject',
            ])
            ->assertForbidden();
    }

    public function test_update_can_partially_update_action(): void
    {
        $user = $this->createUserWithPermission('rule_action.update');
        $rule = $this->createExpertRule();
        $action = $this->createAction($rule);

        $this->actingAs($user)
            ->putJson("/api/v1/rule-actions/{$action->id}", [
                'action_value' => 'reject',
            ])
            ->assertOk()
            ->assertJsonPath('data.action_value', 'reject')
            ->assertJsonPath('data.expert_rule.id', $rule->id);
    }

    public function test_update_can_change_expert_rule(): void
    {
        $user = $this->createUserWithPermission('rule_action.update');
        $rule = $this->createExpertRule();
        $newRule = $this->createExpertRule();
        $action = $this->createAction($rule);

        $this->actingAs($user)
            ->putJson("/api/v1/rule-actions/{$action->id}", [
                'expert_rule_id' => $newRule->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.expert_rule.id', $newRule->id);
    }

    public function test_update_returns_not_found_for_missing_action(): void
    {
        $user = $this->createUserWithPermission('rule_action.update');

        $this->actingAs($user)
            ->putJson('/api/v1/rule-actions/999999', [
                'action_value' => 'reject',
            ])
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $user = $this->createUserWithPermission('rule_action.view');
        $rule = $this->createExpertRule();
        $action = $this->createAction($rule);

        $this->actingAs($user)
            ->deleteJson("/api/v1/rule-actions/{$action->id}")
            ->assertForbidden();
    }

    public function test_destroy_deletes_action_and_returns_null_data(): void
    {
        $user = $this->createUserWithPermission('rule_action.delete');
        $rule = $this->createExpertRule();
        $action = $this->createAction($rule);

        $this->actingAs($user)
            ->deleteJson("/api/v1/rule-actions/{$action->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('rule_actions', [
            'id' => $action->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_action(): void
    {
        $user = $this->createUserWithPermission('rule_action.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/rule-actions/999999')
            ->assertNotFound();
    }
}
