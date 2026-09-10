<?php

namespace Tests\Feature\ExpertSystem;

use App\Models\Knowledge;
use App\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KnowledgeControllerTest extends TestCase
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
        KnowledgeCategory $category,
        array $overrides = [],
    ): Knowledge {
        return Knowledge::query()->create(array_merge([
            'knowledge_category_id' => $category->id,
            'name' => 'Promotion Knowledge',
            'description' => 'Promotion knowledge description',
            'version' => 1,
            'status' => 'active',
        ], $overrides));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/knowledge')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_knowledge(): void
    {
        $user = $this->createUserWithPermission('knowledge.view');
        $category = $this->createCategory();

        $this->createKnowledge($category, ['name' => 'Promotion Knowledge']);
        $this->createKnowledge($category, ['name' => 'Performance Knowledge']);

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_index_can_search_by_name_or_description(): void
    {
        $user = $this->createUserWithPermission('knowledge.view');
        $category = $this->createCategory();

        $this->createKnowledge($category, [
            'name' => 'Promotion Criteria',
            'description' => 'Criteria for promotion assessment',
        ]);
        $this->createKnowledge($category, [
            'name' => 'Training Rules',
            'description' => 'Rules for employee training',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge?search=Promotion')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Promotion Criteria');

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge?search=employee training')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Training Rules');
    }

    public function test_index_can_filter_by_knowledge_category(): void
    {
        $user = $this->createUserWithPermission('knowledge.view');
        $category = $this->createCategory(['name' => 'Promotion Category']);
        $otherCategory = $this->createCategory(['name' => 'Training Category']);

        $this->createKnowledge($category);
        $this->createKnowledge($otherCategory, ['name' => 'Training Knowledge']);

        $this->actingAs($user)
            ->getJson("/api/v1/knowledge?knowledge_category_id={$category->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.knowledge_category.id',
                $category->id,
            );
    }

    public function test_show_returns_knowledge_with_relationships(): void
    {
        $user = $this->createUserWithPermission('knowledge.view');
        $category = $this->createCategory();
        $knowledge = $this->createKnowledge($category);

        $this->actingAs($user)
            ->getJson("/api/v1/knowledge/{$knowledge->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $knowledge->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'knowledge_category',
                    'name',
                    'description',
                    'version',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_returns_not_found_for_missing_knowledge(): void
    {
        $user = $this->createUserWithPermission('knowledge.view');

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge/999999')
            ->assertNotFound();
    }

    public function test_store_creates_knowledge(): void
    {
        $user = $this->createUserWithPermission('knowledge.create');
        $category = $this->createCategory();

        $this->actingAs($user)
            ->postJson('/api/v1/knowledge', [
                'knowledge_category_id' => $category->id,
                'name' => 'Promotion Knowledge',
                'description' => 'Promotion rules and criteria',
                'version' => 2,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.knowledge_category.id',
                $category->id,
            )
            ->assertJsonPath('data.name', 'Promotion Knowledge');

        $this->assertDatabaseHas('knowledge', [
            'knowledge_category_id' => $category->id,
            'name' => 'Promotion Knowledge',
            'version' => 2,
        ]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/knowledge', [])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->createUserWithPermission('knowledge.create');

        $this->actingAs($user)
            ->postJson('/api/v1/knowledge', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_category_id',
                'name',
                'description',
                'status',
            ]);
    }

    public function test_store_validates_category_and_field_lengths(): void
    {
        $user = $this->createUserWithPermission('knowledge.create');

        $this->actingAs($user)
            ->postJson('/api/v1/knowledge', [
                'knowledge_category_id' => 999999,
                'name' => str_repeat('x', 201),
                'description' => 'Valid description',
                'version' => 1,
                'status' => str_repeat('x', 31),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'knowledge_category_id',
                'name',
                'status',
            ]);
    }

    public function test_update_requires_permission(): void
    {
        $owner = $this->createUserWithPermission('knowledge.view');
        $user = User::factory()->create();
        $category = $this->createCategory();
        $knowledge = $this->createKnowledge($category);

        $this->actingAs($user)
            ->putJson("/api/v1/knowledge/{$knowledge->id}", [
                'name' => 'Updated Knowledge',
            ])
            ->assertForbidden();
    }

    public function test_update_can_partially_update_knowledge(): void
    {
        $user = $this->createUserWithPermission('knowledge.update');
        $category = $this->createCategory();
        $knowledge = $this->createKnowledge($category);

        $this->actingAs($user)
            ->putJson("/api/v1/knowledge/{$knowledge->id}", [
                'name' => 'Updated Knowledge',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Knowledge')
            ->assertJsonPath(
                'data.knowledge_category.id',
                $category->id,
            )
            ->assertJsonPath('data.version', 1);
    }

    public function test_update_can_change_category(): void
    {
        $user = $this->createUserWithPermission('knowledge.update');
        $category = $this->createCategory();
        $newCategory = $this->createCategory(['name' => 'New Category']);
        $knowledge = $this->createKnowledge($category);

        $this->actingAs($user)
            ->putJson("/api/v1/knowledge/{$knowledge->id}", [
                'knowledge_category_id' => $newCategory->id,
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.knowledge_category.id',
                $newCategory->id,
            );
    }

    public function test_update_returns_not_found_for_missing_knowledge(): void
    {
        $user = $this->createUserWithPermission('knowledge.update');

        $this->actingAs($user)
            ->putJson('/api/v1/knowledge/999999', [
                'name' => 'Updated',
            ])
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $user = $this->createUserWithPermission('knowledge.view');
        $category = $this->createCategory();
        $knowledge = $this->createKnowledge($category);

        $this->actingAs($user)
            ->deleteJson("/api/v1/knowledge/{$knowledge->id}")
            ->assertForbidden();
    }

    public function test_destroy_deletes_knowledge_and_returns_null_data(): void
    {
        $user = $this->createUserWithPermission('knowledge.delete');
        $category = $this->createCategory();
        $knowledge = $this->createKnowledge($category);

        $this->actingAs($user)
            ->deleteJson("/api/v1/knowledge/{$knowledge->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('knowledge', [
            'id' => $knowledge->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_knowledge(): void
    {
        $user = $this->createUserWithPermission('knowledge.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/knowledge/999999')
            ->assertNotFound();
    }
}
