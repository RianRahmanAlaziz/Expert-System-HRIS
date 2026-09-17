<?php

namespace Tests\Unit\ExpertSystem;

use App\Models\ActivityLog;
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

    public function test_it_logs_activity_when_creating_expert_rule(): void
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

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'create',
            'module' => 'expert_rule',
            'target_type' => $rule->getMorphClass(),
            'target_id' => $rule->id,
        ]);
    }

    public function test_it_logs_activity_when_updating_expert_rule(): void
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

        app(ExpertRuleService::class)->update(
            $rule,
            [
                'name' => 'Performance Sangat Tinggi',
                'priority' => 2,
                'status' => 'inactive',
            ],
        );

        $log = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'expert_rule')
            ->where('target_id', $rule->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'Performance Tinggi',
            $log->old_values['name'],
        );

        $this->assertSame(
            'Performance Sangat Tinggi',
            $log->new_values['name'],
        );

        $this->assertSame(
            1,
            $log->old_values['priority'],
        );

        $this->assertSame(
            2,
            $log->new_values['priority'],
        );

        $this->assertSame(
            'active',
            $log->old_values['status'],
        );

        $this->assertSame(
            'inactive',
            $log->new_values['status'],
        );
    }

    public function test_it_logs_activity_before_deleting_expert_rule(): void
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

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'delete',
            'module' => 'expert_rule',
            'target_type' => $rule->getMorphClass(),
            'target_id' => $rule->id,
        ]);
    }
}
