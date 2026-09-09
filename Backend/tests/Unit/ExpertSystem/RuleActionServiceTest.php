<?php

namespace Tests\Unit\ExpertSystem;

use App\Models\ExpertRule;
use App\Models\Knowledge;
use App\Models\KnowledgeCategory;
use App\Models\RuleAction;
use App\Services\ExpertSystem\RuleActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuleActionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_rule_action(): void
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

        $action = app(RuleActionService::class)->create([
            'expert_rule_id' => $rule->id,
            'action_type' => 'recommendation',
            'action_value' => 'eligible_for_promotion',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        $this->assertInstanceOf(
            RuleAction::class,
            $action,
        );

        $this->assertDatabaseHas('rule_actions', [
            'id' => $action->id,
            'expert_rule_id' => $rule->id,
            'action_type' => 'recommendation',
            'action_value' => 'eligible_for_promotion',
        ]);
    }

    public function test_it_can_find_rule_action(): void
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

        $action = RuleAction::query()->create([
            'expert_rule_id' => $rule->id,
            'action_type' => 'recommendation',
            'action_value' => 'eligible_for_promotion',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        $result = app(RuleActionService::class)
            ->findById($action->id);

        $this->assertTrue(
            $result->is($action),
        );
    }

    public function test_it_can_update_rule_action(): void
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

        $action = RuleAction::query()->create([
            'expert_rule_id' => $rule->id,
            'action_type' => 'recommendation',
            'action_value' => 'eligible_for_promotion',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        $result = app(RuleActionService::class)->update(
            $action,
            [
                'action_type' => 'recommendation',
                'action_value' => 'needs_development',
                'description' => 'Karyawan membutuhkan pengembangan.',
            ],
        );

        $this->assertSame(
            'recommendation',
            $result->action_type,
        );

        $this->assertSame(
            'needs_development',
            $result->action_value,
        );

        $this->assertSame(
            'Karyawan membutuhkan pengembangan.',
            $result->description,
        );
    }

    public function test_it_can_delete_rule_action(): void
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

        $action = RuleAction::query()->create([
            'expert_rule_id' => $rule->id,
            'action_type' => 'recommendation',
            'action_value' => 'eligible_for_promotion',
            'description' => 'Karyawan direkomendasikan untuk promosi.',
        ]);

        app(RuleActionService::class)->delete($action);

        $this->assertDatabaseMissing(
            'rule_actions',
            ['id' => $action->id],
        );
    }
}
