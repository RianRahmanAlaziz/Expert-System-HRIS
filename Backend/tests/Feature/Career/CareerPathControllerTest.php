<?php

namespace Tests\Feature\Career;

use App\Models\CareerPath;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CareerPathControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'career_path.view',
            'career_path.create',
            'career_path.update',
            'career_path.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::create([
            'name' => 'career-path-tester',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permissions);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    private function createCareerPath(array $overrides = []): CareerPath
    {
        return CareerPath::query()->create(array_merge([
            'name' => 'Engineering Career Path',
            'description' => 'Career path for engineering roles.',
            'status' => 'active',
            'is_active' => true,
        ], $overrides));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/career-paths')
            ->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/career-paths')
            ->assertForbidden();
    }

    public function test_index_can_list_career_paths(): void
    {
        $careerPath = $this->createCareerPath();

        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths')
            ->assertOk()
            ->assertJsonPath('data.0.id', $careerPath->id)
            ->assertJsonPath('meta.pagination.total', 1);
    }


    public function test_index_can_search_by_name(): void
    {
        $this->createCareerPath(['name' => 'Software Engineering']);
        $this->createCareerPath(['name' => 'Finance']);

        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths?search=Software')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Software Engineering');
    }

    public function test_index_trims_search(): void
    {
        $this->createCareerPath([
            'name' => 'Engineering Career Path',
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths?search=%20Engineering%20%20')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_can_filter_by_status(): void
    {
        $this->createCareerPath(['status' => 'active']);
        $this->createCareerPath(['status' => 'inactive']);

        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'inactive');
    }

    public function test_index_can_filter_by_is_active(): void
    {
        $this->createCareerPath(['is_active' => true]);
        $this->createCareerPath(['is_active' => false]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths?is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_index_supports_pagination(): void
    {
        $this->createCareerPath(['name' => 'Engineering Career Path',]);
        $this->createCareerPath(['name' => 'Management Career Path',]);
        $this->createCareerPath(['name' => 'Finance Career Path',]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_index_clamps_per_page_to_maximum_100(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_index_clamps_per_page_to_minimum_1(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 1);
    }

    public function test_show_can_display_career_path(): void
    {
        $careerPath = $this->createCareerPath();

        $this->actingAs($this->user)
            ->getJson("/api/v1/career-paths/{$careerPath->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $careerPath->id);
    }

    public function test_show_returns_not_found_for_missing_career_path(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/career-paths/999999')
            ->assertNotFound();
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/career-paths', [
                'name' => 'New Career Path',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_store_can_create_career_path(): void
    {
        $payload = [
            'name' => 'New Career Path',
            'description' => 'New description.',
            'status' => 'active',
            'is_active' => true,
        ];

        $this->actingAs($this->user)
            ->postJson('/api/v1/career-paths', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Career Path');

        $this->assertDatabaseHas('career_paths', [
            'name' => 'New Career Path',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/career-paths', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'status',
            ]);
    }


    public function test_update_requires_permission(): void
    {
        $careerPath = $this->createCareerPath();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson("/api/v1/career-paths/{$careerPath->id}", [
                'name' => 'Updated',
            ])
            ->assertForbidden();
    }

    public function test_update_can_update_career_path(): void
    {
        $careerPath = $this->createCareerPath();

        $this->actingAs($this->user)
            ->putJson("/api/v1/career-paths/{$careerPath->id}", [
                'name' => 'Updated Career Path',
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Career Path')
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('career_paths', [
            'id' => $careerPath->id,
            'name' => 'Updated Career Path',
            'status' => 'inactive',
        ]);
    }

    public function test_update_supports_partial_update(): void
    {
        $careerPath = $this->createCareerPath([
            'name' => 'Original Name',
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/career-paths/{$careerPath->id}", [
                'name' => 'Updated Name',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }


    public function test_update_returns_not_found_for_missing_career_path(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/v1/career-paths/999999', [
                'name' => 'Updated',
            ])
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $careerPath = $this->createCareerPath();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/career-paths/{$careerPath->id}")
            ->assertForbidden();
    }

    public function test_destroy_can_delete_career_path(): void
    {
        $careerPath = $this->createCareerPath();

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/career-paths/{$careerPath->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('career_paths', [
            'id' => $careerPath->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_career_path(): void
    {
        $this->actingAs($this->user)
            ->deleteJson('/api/v1/career-paths/999999')
            ->assertNotFound();
    }

    public function test_resource_returns_expected_fields(): void
    {
        $careerPath = $this->createCareerPath([
            'description' => 'Description',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/career-paths/{$careerPath->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'is_active',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }
}
