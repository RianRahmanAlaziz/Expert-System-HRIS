<?php

namespace Tests\Unit\ExpertSystem;

use App\Models\ActivityLog;
use App\Models\KnowledgeCategory;
use App\Services\ExpertSystem\KnowledgeCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeCategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_knowledge_category(): void
    {
        $service = app(KnowledgeCategoryService::class);

        $category = $service->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(
            KnowledgeCategory::class,
            $category,
        );

        $this->assertDatabaseHas('knowledge_categories', [
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'status' => 'active',
        ]);
    }

    public function test_it_can_find_knowledge_category(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $result = app(KnowledgeCategoryService::class)
            ->findById($category->id);

        $this->assertTrue(
            $result->is($category),
        );
    }

    public function test_it_can_update_knowledge_category(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $result = app(KnowledgeCategoryService::class)->update(
            $category,
            [
                'name' => 'Performance Management',
                'status' => 'inactive',
            ],
        );

        $this->assertSame(
            'Performance Management',
            $result->name,
        );

        $this->assertSame(
            'inactive',
            $result->status,
        );
    }

    public function test_it_can_delete_knowledge_category(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => null,
            'status' => 'active',
        ]);

        app(KnowledgeCategoryService::class)->delete($category);

        $this->assertDatabaseMissing(
            'knowledge_categories',
            ['id' => $category->id],
        );
    }

    public function test_it_logs_activity_when_creating_knowledge_category(): void
    {
        $category = app(KnowledgeCategoryService::class)->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'create',
            'module' => 'knowledge_category',
            'target_type' => $category->getMorphClass(),
            'target_id' => $category->id,
        ]);
    }

    public function test_it_logs_activity_when_updating_knowledge_category(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        app(KnowledgeCategoryService::class)->update(
            $category,
            [
                'name' => 'Performance Management',
                'status' => 'inactive',
            ],
        );

        $log = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'knowledge_category')
            ->where('target_id', $category->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'Performance',
            $log->old_values['name'],
        );

        $this->assertSame(
            'Performance Management',
            $log->new_values['name'],
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

    public function test_it_logs_activity_before_deleting_knowledge_category(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => null,
            'status' => 'active',
        ]);

        app(KnowledgeCategoryService::class)->delete($category);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'delete',
            'module' => 'knowledge_category',
            'target_type' => $category->getMorphClass(),
            'target_id' => $category->id,
        ]);
    }
}
