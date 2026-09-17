<?php

namespace Tests\Unit\ExpertSystem;

use App\Models\ActivityLog;
use App\Models\ExpertRule;
use App\Models\Knowledge;
use App\Models\KnowledgeCategory;
use App\Models\RuleCondition;
use App\Services\ExpertSystem\RuleConditionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleConditionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_rule_condition(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $knowledge = Knowledge::query()->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'description' => 'Knowledge untuk assessment performance.',
            'version' => 1,
            'status' => 'active',
        ]);

        $rule = ExpertRule::query()->create([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'description' => 'Rule untuk performance tinggi.',
            'priority' => 1,
            'status' => 'active',
        ]);

        $condition = app(RuleConditionService::class)->create([
            'expert_rule_id' => $rule->id,
            'parameter' => 'performance_score',
            'operator' => '>=',
            'value' => '80',
            'logical_operator' => null,
            'sort_order' => 0,
        ]);

        $this->assertInstanceOf(
            RuleCondition::class,
            $condition,
        );

        $this->assertDatabaseHas('rule_conditions', [
            'id' => $condition->id,
            'expert_rule_id' => $rule->id,
            'parameter' => 'performance_score',
            'operator' => '>=',
            'value' => '80',
            'sort_order' => 0,
        ]);
    }

    public function test_it_can_find_rule_condition(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $knowledge = Knowledge::query()->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'description' => 'Knowledge untuk assessment performance.',
            'version' => 1,
            'status' => 'active',
        ]);

        $rule = ExpertRule::query()->create([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'description' => 'Rule untuk performance tinggi.',
            'priority' => 1,
            'status' => 'active',
        ]);

        $condition = RuleCondition::query()->create([
            'expert_rule_id' => $rule->id,
            'parameter' => 'performance_score',
            'operator' => '>=',
            'value' => '80',
            'logical_operator' => null,
            'sort_order' => 0,
        ]);

        $result = app(RuleConditionService::class)
            ->findById($condition->id);

        $this->assertTrue(
            $result->is($condition),
        );
    }

    public function test_it_can_update_rule_condition(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $knowledge = Knowledge::query()->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'description' => 'Knowledge untuk assessment performance.',
            'version' => 1,
            'status' => 'active',
        ]);

        $rule = ExpertRule::query()->create([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'description' => 'Rule untuk performance tinggi.',
            'priority' => 1,
            'status' => 'active',
        ]);

        $condition = RuleCondition::query()->create([
            'expert_rule_id' => $rule->id,
            'parameter' => 'performance_score',
            'operator' => '>=',
            'value' => '80',
            'logical_operator' => null,
            'sort_order' => 0,
        ]);

        $result = app(RuleConditionService::class)->update(
            $condition,
            [
                'parameter' => 'performance_score',
                'operator' => '>',
                'value' => '90',
                'sort_order' => 1,
            ],
        );

        $this->assertSame(
            'performance_score',
            $result->parameter,
        );

        $this->assertSame(
            '>',
            $result->operator,
        );

        $this->assertSame(
            '90',
            $result->value,
        );

        $this->assertSame(
            1,
            $result->sort_order,
        );
    }

    public function test_it_can_delete_rule_condition(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $knowledge = Knowledge::query()->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'description' => 'Knowledge untuk assessment performance.',
            'version' => 1,
            'status' => 'active',
        ]);

        $rule = ExpertRule::query()->create([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'description' => 'Rule untuk performance tinggi.',
            'priority' => 1,
            'status' => 'active',
        ]);

        $condition = RuleCondition::query()->create([
            'expert_rule_id' => $rule->id,
            'parameter' => 'performance_score',
            'operator' => '>=',
            'value' => '80',
            'logical_operator' => null,
            'sort_order' => 0,
        ]);

        app(RuleConditionService::class)->delete($condition);

        $this->assertDatabaseMissing(
            'rule_conditions',
            ['id' => $condition->id],
        );
    }

    public function test_it_logs_activity_when_creating_rule_condition(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $knowledge = Knowledge::query()->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'description' => 'Knowledge untuk assessment performance.',
            'version' => 1,
            'status' => 'active',
        ]);

        $rule = ExpertRule::query()->create([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'description' => 'Rule untuk performance tinggi.',
            'priority' => 1,
            'status' => 'active',
        ]);

        $condition = app(RuleConditionService::class)->create([
            'expert_rule_id' => $rule->id,
            'parameter' => 'performance_score',
            'operator' => '>=',
            'value' => '80',
            'logical_operator' => null,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'create',
            'module' => 'rule_condition',
            'target_type' => $condition->getMorphClass(),
            'target_id' => $condition->id,
        ]);
    }

    public function test_it_logs_activity_when_updating_rule_condition(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $knowledge = Knowledge::query()->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'description' => 'Knowledge untuk assessment performance.',
            'version' => 1,
            'status' => 'active',
        ]);

        $rule = ExpertRule::query()->create([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'description' => 'Rule untuk performance tinggi.',
            'priority' => 1,
            'status' => 'active',
        ]);

        $condition = RuleCondition::query()->create([
            'expert_rule_id' => $rule->id,
            'parameter' => 'performance_score',
            'operator' => '>=',
            'value' => '80',
            'logical_operator' => null,
            'sort_order' => 0,
        ]);

        app(RuleConditionService::class)->update(
            $condition,
            [
                'parameter' => 'performance_score',
                'operator' => '>',
                'value' => '90',
                'sort_order' => 1,
            ],
        );

        $log = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'rule_condition')
            ->where('target_id', $condition->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            '>=',
            $log->old_values['operator'],
        );

        $this->assertSame(
            '>',
            $log->new_values['operator'],
        );

        $this->assertSame(
            '80',
            $log->old_values['value'],
        );

        $this->assertSame(
            '90',
            $log->new_values['value'],
        );

        $this->assertSame(
            0,
            $log->old_values['sort_order'],
        );

        $this->assertSame(
            1,
            $log->new_values['sort_order'],
        );
    }

    public function test_it_logs_activity_before_deleting_rule_condition(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $knowledge = Knowledge::query()->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'description' => 'Knowledge untuk assessment performance.',
            'version' => 1,
            'status' => 'active',
        ]);

        $rule = ExpertRule::query()->create([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'description' => 'Rule untuk performance tinggi.',
            'priority' => 1,
            'status' => 'active',
        ]);

        $condition = RuleCondition::query()->create([
            'expert_rule_id' => $rule->id,
            'parameter' => 'performance_score',
            'operator' => '>=',
            'value' => '80',
            'logical_operator' => null,
            'sort_order' => 0,
        ]);

        app(RuleConditionService::class)->delete($condition);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'delete',
            'module' => 'rule_condition',
            'target_type' => $condition->getMorphClass(),
            'target_id' => $condition->id,
        ]);
    }
}
