<?php

namespace Tests\Feature\ExpertSystem;

use App\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;


class KnowledgeCategoryControllerTest extends TestCase
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

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/knowledge-categories')->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge-categories')
            ->assertForbidden();
    }

    public function test_index_returns_paginated_categories(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.view');
        $this->createCategory(['name' => 'Competency Knowledge']);
        $this->createCategory(['name' => 'Policy Knowledge']);

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge-categories?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_index_can_search_by_name_or_code(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.view');
        $this->createCategory([
            'code' => 'HR-COMP',
            'name' => 'Competency Knowledge',
        ]);
        $this->createCategory([
            'code' => 'POLICY',
            'name' => 'Policy Knowledge',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge-categories?search=HR-COMP')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'HR-COMP');

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge-categories?search=Policy')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Policy Knowledge');
    }

    public function test_show_returns_category_with_knowledge(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.view');
        $category = $this->createCategory();

        $this->actingAs($user)
            ->getJson("/api/v1/knowledge-categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'name',
                    'description',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_returns_not_found_for_missing_category(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.view');

        $this->actingAs($user)
            ->getJson('/api/v1/knowledge-categories/999999')
            ->assertNotFound();
    }

    public function test_store_creates_category(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.create');

        $this->actingAs($user)
            ->postJson('/api/v1/knowledge-categories', [
                'code' => 'HR-COMP',
                'name' => 'Competency Knowledge',
                'description' => 'Competency knowledge category',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'HR-COMP')
            ->assertJsonPath('data.name', 'Competency Knowledge');

        $this->assertDatabaseHas('knowledge_categories', [
            'code' => 'HR-COMP',
            'name' => 'Competency Knowledge',
        ]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/knowledge-categories', [])
            ->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.create');

        $this->actingAs($user)
            ->postJson('/api/v1/knowledge-categories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'name',
                'status',
            ]);
    }

    public function test_store_validates_max_lengths_and_unique_code(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.create');
        $this->createCategory(['code' => 'EXISTING']);

        $this->actingAs($user)
            ->postJson('/api/v1/knowledge-categories', [
                'code' => 'EXISTING',
                'name' => str_repeat('x', 101),
                'status' => str_repeat('x', 31),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'name',
                'status',
            ]);
    }

    public function test_update_requires_permission(): void
    {
        $owner = $this->createUserWithPermission('knowledge_category.view');
        $user = User::factory()->create();
        $category = $this->createCategory();

        $this->actingAs($user)
            ->putJson("/api/v1/knowledge-categories/{$category->id}", [
                'name' => 'Updated',
            ])
            ->assertForbidden();
    }

    public function test_update_can_partially_update_category(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.update');
        $category = $this->createCategory();

        $this->actingAs($user)
            ->putJson("/api/v1/knowledge-categories/{$category->id}", [
                'name' => 'Updated Knowledge',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Knowledge')
            ->assertJsonPath('data.code', $category->code);
    }

    public function test_update_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.update');
        $this->createCategory(['code' => 'FIRST']);
        $second = $this->createCategory(['code' => 'SECOND']);

        $this->actingAs($user)
            ->putJson("/api/v1/knowledge-categories/{$second->id}", [
                'code' => 'FIRST',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_update_returns_not_found_for_missing_category(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.update');

        $this->actingAs($user)
            ->putJson('/api/v1/knowledge-categories/999999', [
                'name' => 'Updated',
            ])
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.view');
        $category = $this->createCategory();

        $this->actingAs($user)
            ->deleteJson("/api/v1/knowledge-categories/{$category->id}")
            ->assertForbidden();
    }

    public function test_destroy_deletes_category_and_returns_null_data(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.delete');
        $category = $this->createCategory();

        $this->actingAs($user)
            ->deleteJson("/api/v1/knowledge-categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('knowledge_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_category(): void
    {
        $user = $this->createUserWithPermission('knowledge_category.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/knowledge-categories/999999')
            ->assertNotFound();
    }
}
