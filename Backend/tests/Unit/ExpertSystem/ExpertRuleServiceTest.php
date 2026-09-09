<?php

namespace Tests\Unit\ExpertSystem;

use App\Models\ExpertRule;
use App\Models\Knowledge;
use App\Models\KnowledgeCategory;
use App\Services\ExpertSystem\ExpertRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpertRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_expert_rule(): void
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

        $rule = app(ExpertRuleService::class)->create([
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'description' => 'Rule untuk performance tinggi.',
            'priority' => 1,
            'status' => 'active',
        ]);

        $this->assertInstanceOf(
            ExpertRule::class,
            $rule,
        );

        $this->assertDatabaseHas('expert_rules', [
            'id' => $rule->id,
            'knowledge_id' => $knowledge->id,
            'code' => 'RULE-PERF-001',
            'name' => 'Performance Tinggi',
            'priority' => 1,
            'status' => 'active',
        ]);
    }

    public function test_it_can_find_expert_rule(): void
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

        $result = app(ExpertRuleService::class)
            ->findById($rule->id);

        $this->assertTrue(
            $result->is($rule),
        );
    }

    public function test_it_can_update_expert_rule(): void
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

        $result = app(ExpertRuleService::class)->update(
            $rule,
            [
                'name' => 'Performance Sangat Tinggi',
                'priority' => 2,
                'status' => 'inactive',
            ],
        );

        $this->assertSame(
            'Performance Sangat Tinggi',
            $result->name,
        );

        $this->assertSame(
            2,
            $result->priority,
        );

        $this->assertSame(
            'inactive',
            $result->status,
        );
    }

    public function test_it_can_delete_expert_rule(): void
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

        app(ExpertRuleService::class)->delete($rule);

        $this->assertDatabaseMissing(
            'expert_rules',
            ['id' => $rule->id],
        );
    }
}
