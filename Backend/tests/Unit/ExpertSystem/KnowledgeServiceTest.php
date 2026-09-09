<?php

namespace Tests\Unit\ExpertSystem;

use App\Models\Knowledge;
use App\Models\KnowledgeCategory;
use App\Services\ExpertSystem\KnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_knowledge(): void
    {
        $category = KnowledgeCategory::query()->create([
            'name' => 'Performance',
            'code' => 'PERFORMANCE',
            'description' => 'Knowledge terkait performance.',
            'status' => 'active',
        ]);

        $knowledge = app(KnowledgeService::class)->create([
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'description' => 'Knowledge untuk assessment performance.',
            'version' => 1,
            'status' => 'active',
        ]);

        $this->assertInstanceOf(
            Knowledge::class,
            $knowledge,
        );

        $this->assertDatabaseHas('knowledge', [
            'id' => $knowledge->id,
            'knowledge_category_id' => $category->id,
            'name' => 'Performance Assessment',
            'version' => 1,
            'status' => 'active',
        ]);
    }

    public function test_it_can_find_knowledge(): void
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

        $result = app(KnowledgeService::class)
            ->findById($knowledge->id);

        $this->assertTrue(
            $result->is($knowledge),
        );
    }

    public function test_it_can_update_knowledge(): void
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

        $result = app(KnowledgeService::class)->update(
            $knowledge,
            [
                'name' => 'Performance Assessment Updated',
                'version' => 2,
                'status' => 'inactive',
            ],
        );

        $this->assertSame(
            'Performance Assessment Updated',
            $result->name,
        );

        $this->assertSame(
            2,
            $result->version,
        );

        $this->assertSame(
            'inactive',
            $result->status,
        );
    }

    public function test_it_can_delete_knowledge(): void
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

        app(KnowledgeService::class)->delete($knowledge);

        $this->assertDatabaseMissing(
            'knowledge',
            ['id' => $knowledge->id],
        );
    }
}
