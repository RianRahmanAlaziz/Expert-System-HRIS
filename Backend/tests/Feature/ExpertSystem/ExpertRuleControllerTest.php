<?php

namespace Tests\Feature\ExpertSystem;

use App\Models\ExpertRule;
use App\Models\Knowledge;
use App\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExpertRuleControllerTest extends TestCase
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

    private function createCategory(array $overrides = []): KnowledgeCategory
    {
        return KnowledgeCategory::query()->create(array_merge([
            'code' => 'KC-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Competency Knowledge',
            'description' => 'Knowledge category description',
            'status' => 'active',
        ], $overrides));
    }

    private function createKnowledge(
        ?KnowledgeCategory $category = null,
        array $overrides = [],
    ): Knowledge {
        $category ??= $this->createCategory();

        return Knowledge::query()->create(array_merge([
            'knowledge_category_id' => $category->id,
            'name' => 'Promotion Knowledge',
            'description' => 'Promotion knowledge description',
            'version' => 1,
            'status' => 'active',
        ], $overrides));
    }

    private function createExpertRule(
        Knowledge $knowledge,
        array $overrides = [],
    ): ExpertRule {
        return ExpertRule::query()->create(array_merge([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Promotion Eligibility Rule',
            'description' => 'Promotion eligibility rule description',
            'priority' => 1,
            'status' => 'active',
        ], $overrides));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/expert-rules')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/expert-rules')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_rules(): void
    {
        $user = $this->createUserWithPermission('expert_rule.view');
        $knowledge = $this->createKnowledge();

        $this->createExpertRule($knowledge);
        $this->createExpertRule($knowledge);

        $this->actingAs($user)
            ->getJson('/api/v1/expert-rules?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_index_can_search_by_code_or_name(): void
    {
        $user = $this->createUserWithPermission('expert_rule.view');
        $knowledge = $this->createKnowledge();

        $this->createExpertRule($knowledge, [
            'code' => 'PROMOTION-RULE',
            'name' => 'Promotion Eligibility',
        ]);
        $this->createExpertRule($knowledge, [
            'code' => 'TRAINING-RULE',
            'name' => 'Training Eligibility',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/expert-rules?search=PROMOTION-RULE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'PROMOTION-RULE');

        $this->actingAs($user)
            ->getJson('/api/v1/expert-rules?search=Training Eligibility')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Training Eligibility');
    }

    public function test_index_can_filter_by_knowledge(): void
    {
        $user = $this->createUserWithPermission('expert_rule.view');
        $knowledge = $this->createKnowledge();
        $otherKnowledge = $this->createKnowledge();

        $this->createExpertRule($knowledge, [
            'name' => 'Promotion Rule',
        ]);
        $this->createExpertRule($otherKnowledge, [
            'name' => 'Training Rule',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/expert-rules?knowledge_id={$knowledge->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.knowledge.id', $knowledge->id);
    }

    public function test_show_returns_rule_with_knowledge(): void
    {
        $user = $this->createUserWithPermission('expert_rule.view');
        $knowledge = $this->createKnowledge();
        $rule = $this->createExpertRule($knowledge);

        $this->actingAs($user)
            ->getJson("/api/v1/expert-rules/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $rule->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'knowledge',
                    'code',
                    'name',
                    'description',
                    'priority',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_returns_not_found_for_missing_rule(): void
    {
        $user = $this->createUserWithPermission('expert_rule.view');

        $this->actingAs($user)
            ->getJson('/api/v1/expert-rules/999999')
            ->assertNotFound();
    }

    public function test_store_creates_rule(): void
    {
        $user = $this->createUserWithPermission('expert_rule.create');
        $knowledge = $this->createKnowledge();

        $this->actingAs($user)
            ->postJson('/api/v1/expert-rules', [
                'knowledge_id' => $knowledge->id,
                'code' => 'PROMOTION-RULE',
                'name' => 'Promotion Eligibility',
                'description' => 'Promotion rule',
                'priority' => 10,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.knowledge.id', $knowledge->id)
            ->assertJsonPath('data.code', 'PROMOTION-RULE')
            ->assertJsonPath('data.name', 'Promotion Eligibility');

        $this->assertDatabaseHas('expert_rules', [
            'knowledge_id' => $knowledge->id,
            'code' => 'PROMOTION-RULE',
            'priority' => 10,
        ]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/expert-rules', [])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->createUserWithPermission('expert_rule.create');

        $this->actingAs($user)
            ->postJson('/api/v1/expert-rules', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_id',
                'code',
                'name',
                'status',
            ]);
    }

    public function test_store_validates_foreign_key_and_field_lengths(): void
    {
        $user = $this->createUserWithPermission('expert_rule.create');

        $this->actingAs($user)
            ->postJson('/api/v1/expert-rules', [
                'knowledge_id' => 999999,
                'code' => str_repeat('x', 51),
                'name' => 'Valid Rule Name',
                'priority' => 0,
                'status' => str_repeat('x', 31),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_id',
                'code',
                'priority',
                'status',
            ]);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('expert_rule.create');
        $knowledge = $this->createKnowledge();

        $this->createExpertRule($knowledge, [
            'code' => 'DUPLICATE-RULE',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/expert-rules', [
                'knowledge_id' => $knowledge->id,
                'code' => 'DUPLICATE-RULE',
                'name' => 'Another Rule',
                'priority' => 2,
                'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_update_requires_permission(): void
    {
        $owner = $this->createUserWithPermission('expert_rule.view');
        $user = User::factory()->create();
        $knowledge = $this->createKnowledge();
        $rule = $this->createExpertRule($knowledge);

        $this->actingAs($user)
            ->putJson("/api/v1/expert-rules/{$rule->id}", [
                'name' => 'Updated Rule',
            ])
            ->assertForbidden();
    }

    public function test_update_can_partially_update_rule(): void
    {
        $user = $this->createUserWithPermission('expert_rule.update');
        $knowledge = $this->createKnowledge();
        $rule = $this->createExpertRule($knowledge);

        $this->actingAs($user)
            ->putJson("/api/v1/expert-rules/{$rule->id}", [
                'name' => 'Updated Rule',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Rule')
            ->assertJsonPath('data.knowledge.id', $knowledge->id)
            ->assertJsonPath('data.priority', 1);
    }

    public function test_update_can_change_knowledge(): void
    {
        $user = $this->createUserWithPermission('expert_rule.update');
        $knowledge = $this->createKnowledge();
        $newKnowledge = $this->createKnowledge();
        $rule = $this->createExpertRule($knowledge);

        $this->actingAs($user)
            ->putJson("/api/v1/expert-rules/{$rule->id}", [
                'knowledge_id' => $newKnowledge->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.knowledge.id', $newKnowledge->id);
    }

    public function test_update_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('expert_rule.update');
        $knowledge = $this->createKnowledge();
        $this->createExpertRule($knowledge, [
            'code' => 'FIRST-RULE',
        ]);
        $second = $this->createExpertRule($knowledge, [
            'code' => 'SECOND-RULE',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/expert-rules/{$second->id}", [
                'code' => 'FIRST-RULE',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_update_returns_not_found_for_missing_rule(): void
    {
        $user = $this->createUserWithPermission('expert_rule.update');

        $this->actingAs($user)
            ->putJson('/api/v1/expert-rules/999999', [
                'name' => 'Updated Rule',
            ])
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $user = $this->createUserWithPermission('expert_rule.view');
        $knowledge = $this->createKnowledge();
        $rule = $this->createExpertRule($knowledge);

        $this->actingAs($user)
            ->deleteJson("/api/v1/expert-rules/{$rule->id}")
            ->assertForbidden();
    }

    public function test_destroy_deletes_rule_and_returns_null_data(): void
    {
        $user = $this->createUserWithPermission('expert_rule.delete');
        $knowledge = $this->createKnowledge();
        $rule = $this->createExpertRule($knowledge);

        $this->actingAs($user)
            ->deleteJson("/api/v1/expert-rules/{$rule->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('expert_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_rule(): void
    {
        $user = $this->createUserWithPermission('expert_rule.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/expert-rules/999999')
            ->assertNotFound();
    }
}
