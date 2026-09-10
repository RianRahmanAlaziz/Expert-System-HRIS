<?php

namespace Tests\Feature\Training;

use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TrainingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithPermission(string $permission): User
    {
        $user = User::factory()->create();

        $permissionModel = Permission::findOrCreate(
            $permission,
            'web',
        );

        $user->givePermissionTo($permissionModel);

        return $user;
    }

    private function createTraining(
        ?string $code = null,
        ?string $name = null,
        ?string $category = null,
        ?string $description = null,
        ?string $trainer = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $capacity = null,
        ?string $status = null,
    ): Training {
        return Training::query()->create([
            'code' => $code ?? fake()->unique()->bothify('TRN-####'),
            'name' => $name ?? 'Laravel Training',
            'category' => $category ?? 'Technical',
            'description' => $description ?? 'Training for testing.',
            'trainer' => $trainer ?? 'Test Trainer',
            'start_date' => $startDate ?? '2026-09-10',
            'end_date' => $endDate ?? '2026-09-12',
            'capacity' => $capacity ?? 20,
            'status' => $status ?? 'scheduled',
        ]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/trainings')
            ->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/trainings')
            ->assertForbidden();
    }

    public function test_index_can_list_trainings(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $training = $this->createTraining();

        $this->actingAs($user)
            ->getJson('/api/v1/trainings')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $training->id,
                'code' => $training->code,
                'name' => $training->name,
            ]);
    }

    public function test_index_can_search_by_code(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $training = $this->createTraining(code: 'TRN-SEARCH-CODE');
        $other = $this->createTraining(code: 'TRN-OTHER-CODE');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/trainings?search=SEARCH-CODE')
            ->assertOk();

        $response->assertJsonFragment(['id' => $training->id]);
        $response->assertJsonMissing(['id' => $other->id]);
    }

    public function test_index_can_search_by_name(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $training = $this->createTraining(name: 'Advanced Leadership Program');
        $other = $this->createTraining(name: 'Basic Safety Program');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/trainings?search=Leadership')
            ->assertOk();

        $response->assertJsonFragment(['id' => $training->id]);
        $response->assertJsonMissing(['id' => $other->id]);
    }

    public function test_index_can_search_by_category(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $training = $this->createTraining(category: 'Management');
        $other = $this->createTraining(category: 'Technical');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/trainings?search=Management')
            ->assertOk();

        $response->assertJsonFragment(['id' => $training->id]);
        $response->assertJsonMissing(['id' => $other->id]);
    }

    public function test_index_can_search_by_description(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $training = $this->createTraining(
            description: 'Special training for expert system development.',
        );
        $other = $this->createTraining(
            description: 'General onboarding material.',
        );

        $response = $this->actingAs($user)
            ->getJson('/api/v1/trainings?search=expert%20system')
            ->assertOk();

        $response->assertJsonFragment(['id' => $training->id]);
        $response->assertJsonMissing(['id' => $other->id]);
    }

    public function test_index_trims_search(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $training = $this->createTraining(
            name: 'Trimmed Search Training',
        );

        $this->actingAs($user)
            ->getJson('/api/v1/trainings?search=%20Trimmed%20')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $training->id,
            ]);
    }

    public function test_index_supports_pagination(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $this->createTraining();
        $this->createTraining();
        $this->createTraining();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/trainings?per_page=2')
            ->assertOk();

        $response->assertJsonPath('meta.pagination.per_page', 2);
        $response->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_index_clamps_per_page_to_maximum_100(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $this->actingAs($user)
            ->getJson('/api/v1/trainings?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_index_clamps_per_page_to_minimum_1(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $this->actingAs($user)
            ->getJson('/api/v1/trainings?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 1);
    }

    public function test_show_can_display_training(): void
    {
        $user = $this->createUserWithPermission('training.view');
        $training = $this->createTraining();

        $this->actingAs($user)
            ->getJson("/api/v1/trainings/{$training->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $training->id,
                'code' => $training->code,
            ]);
    }

    public function test_show_returns_not_found_for_missing_training(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $this->actingAs($user)
            ->getJson('/api/v1/trainings/999999')
            ->assertNotFound();
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/trainings', [
                'code' => 'TRN-001',
                'name' => 'Test Training',
                'start_date' => '2026-09-10',
                'end_date' => '2026-09-12',
                'status' => 'scheduled',
            ])
            ->assertForbidden();
    }

    public function test_store_can_create_training(): void
    {
        $user = $this->createUserWithPermission('training.create');

        $payload = [
            'code' => 'TRN-001',
            'name' => 'Leadership Training',
            'category' => 'Management',
            'description' => 'Leadership development.',
            'trainer' => 'John Trainer',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'capacity' => 25,
            'status' => 'scheduled',
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/trainings', $payload)
            ->assertCreated()
            ->assertJsonFragment([
                'code' => 'TRN-001',
                'name' => 'Leadership Training',
            ]);

        $this->assertDatabaseHas('trainings', [
            'code' => 'TRN-001',
            'name' => 'Leadership Training',
            'capacity' => 25,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->createUserWithPermission('training.create');

        $this->actingAs($user)
            ->postJson('/api/v1/trainings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'name',
                'start_date',
                'end_date',
                'status',
            ]);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('training.create');
        $training = $this->createTraining(code: 'TRN-DUPLICATE');

        $this->actingAs($user)
            ->postJson('/api/v1/trainings', [
                'code' => $training->code,
                'name' => 'Another Training',
                'start_date' => '2026-09-20',
                'end_date' => '2026-09-22',
                'status' => 'scheduled',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $user = $this->createUserWithPermission('training.create');

        $this->actingAs($user)
            ->postJson('/api/v1/trainings', [
                'code' => 'TRN-DATE-001',
                'name' => 'Invalid Date Training',
                'start_date' => '2026-09-20',
                'end_date' => '2026-09-19',
                'status' => 'scheduled',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_store_accepts_same_start_and_end_date(): void
    {
        $user = $this->createUserWithPermission('training.create');

        $this->actingAs($user)
            ->postJson('/api/v1/trainings', [
                'code' => 'TRN-SAME-DATE',
                'name' => 'One Day Training',
                'start_date' => '2026-09-20',
                'end_date' => '2026-09-20',
                'status' => 'scheduled',
            ])
            ->assertCreated();
    }

    public function test_store_rejects_invalid_capacity(): void
    {
        $user = $this->createUserWithPermission('training.create');

        $this->actingAs($user)
            ->postJson('/api/v1/trainings', [
                'code' => 'TRN-CAPACITY',
                'name' => 'Capacity Training',
                'start_date' => '2026-09-20',
                'end_date' => '2026-09-22',
                'capacity' => 0,
                'status' => 'scheduled',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['capacity']);
    }

    public function test_update_requires_permission(): void
    {
        $user = User::factory()->create();
        $training = $this->createTraining();

        $this->actingAs($user)
            ->putJson("/api/v1/trainings/{$training->id}", [
                'name' => 'Updated Training',
            ])
            ->assertForbidden();
    }

    public function test_update_can_update_training(): void
    {
        $user = $this->createUserWithPermission('training.update');
        $training = $this->createTraining();

        $this->actingAs($user)
            ->putJson("/api/v1/trainings/{$training->id}", [
                'code' => 'TRN-UPDATED',
                'name' => 'Updated Training',
                'category' => 'Leadership',
                'description' => 'Updated description.',
                'trainer' => 'Updated Trainer',
                'start_date' => '2026-09-15',
                'end_date' => '2026-09-18',
                'capacity' => 40,
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'code' => 'TRN-UPDATED',
                'name' => 'Updated Training',
            ]);

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'code' => 'TRN-UPDATED',
            'capacity' => 40,
        ]);
    }

    public function test_update_supports_partial_update(): void
    {
        $user = $this->createUserWithPermission('training.update');

        $training = $this->createTraining(
            category: 'Technical',
            capacity: 20,
        );

        $this->actingAs($user)
            ->patchJson("/api/v1/trainings/{$training->id}", [
                'name' => 'Partial Update Training',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Partial Update Training',
            ]);

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'category' => 'Technical',
            'capacity' => 20,
        ]);
    }

    public function test_update_rejects_duplicate_code(): void
    {
        $user = $this->createUserWithPermission('training.update');

        $this->createTraining(code: 'TRN-EXISTING');
        $training = $this->createTraining(code: 'TRN-CURRENT');

        $this->actingAs($user)
            ->putJson("/api/v1/trainings/{$training->id}", [
                'code' => 'TRN-EXISTING',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_update_allows_same_code(): void
    {
        $user = $this->createUserWithPermission('training.update');
        $training = $this->createTraining(code: 'TRN-SAME-CODE');

        $this->actingAs($user)
            ->putJson("/api/v1/trainings/{$training->id}", [
                'code' => 'TRN-SAME-CODE',
            ])
            ->assertOk();
    }

    public function test_update_rejects_end_date_before_persisted_start_date(): void
    {
        $user = $this->createUserWithPermission('training.update');

        $training = $this->createTraining(
            startDate: '2026-09-20',
            endDate: '2026-09-25',
        );

        $this->actingAs($user)
            ->patchJson("/api/v1/trainings/{$training->id}", [
                'end_date' => '2026-09-19',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_update_rejects_start_date_after_persisted_end_date(): void
    {
        $user = $this->createUserWithPermission('training.update');

        $training = $this->createTraining(
            startDate: '2026-09-20',
            endDate: '2026-09-25',
        );

        $this->actingAs($user)
            ->patchJson("/api/v1/trainings/{$training->id}", [
                'start_date' => '2026-09-26',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_update_returns_not_found_for_missing_training(): void
    {
        $user = $this->createUserWithPermission('training.update');

        $this->actingAs($user)
            ->putJson('/api/v1/trainings/999999', [
                'name' => 'Missing Training',
            ])
            ->assertNotFound();
    }

    public function test_update_status_requires_permission(): void
    {
        $user = User::factory()->create();
        $training = $this->createTraining();

        $this->actingAs($user)
            ->patchJson("/api/v1/trainings/{$training->id}/status", [
                'status' => 'completed',
            ])
            ->assertForbidden();
    }

    public function test_update_status_can_update_status(): void
    {
        $user = $this->createUserWithPermission('training.status.update');
        $training = $this->createTraining(status: 'scheduled');

        $this->actingAs($user)
            ->patchJson("/api/v1/trainings/{$training->id}/status", [
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'completed',
            ]);

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'status' => 'completed',
        ]);
    }

    public function test_update_status_requires_status(): void
    {
        $user = $this->createUserWithPermission('training.status.update');
        $training = $this->createTraining();

        $this->actingAs($user)
            ->patchJson("/api/v1/trainings/{$training->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_status_returns_not_found_for_missing_training(): void
    {
        $user = $this->createUserWithPermission('training.status.update');

        $this->actingAs($user)
            ->patchJson('/api/v1/trainings/999999/status', [
                'status' => 'completed',
            ])
            ->assertNotFound();
    }

    public function test_destroy_requires_permission(): void
    {
        $user = User::factory()->create();
        $training = $this->createTraining();

        $this->actingAs($user)
            ->deleteJson("/api/v1/trainings/{$training->id}")
            ->assertForbidden();
    }

    public function test_destroy_can_delete_training(): void
    {
        $user = $this->createUserWithPermission('training.delete');
        $training = $this->createTraining();

        $this->actingAs($user)
            ->deleteJson("/api/v1/trainings/{$training->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('trainings', [
            'id' => $training->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_training(): void
    {
        $user = $this->createUserWithPermission('training.delete');

        $this->actingAs($user)
            ->deleteJson('/api/v1/trainings/999999')
            ->assertNotFound();
    }

    public function test_resource_returns_expected_fields(): void
    {
        $user = $this->createUserWithPermission('training.view');

        $training = $this->createTraining(
            category: 'Management',
            description: 'Training description.',
            trainer: 'Trainer Name',
            capacity: 30,
            status: 'scheduled',
        );

        $this->actingAs($user)
            ->getJson("/api/v1/trainings/{$training->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $training->id)
            ->assertJsonPath('data.code', $training->code)
            ->assertJsonPath('data.name', $training->name)
            ->assertJsonPath('data.category', 'Management')
            ->assertJsonPath('data.description', 'Training description.')
            ->assertJsonPath('data.trainer', 'Trainer Name')
            ->assertJsonPath('data.start_date', '2026-09-10')
            ->assertJsonPath('data.end_date', '2026-09-12')
            ->assertJsonPath('data.capacity', 30)
            ->assertJsonPath('data.status', 'scheduled');
    }
}
