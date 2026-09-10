<?php

namespace Tests\Feature\Training;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainingParticipantControllerTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $permissions = []): User
    {
        $user = User::factory()->create();

        $role = Role::query()->create([
            'name' => 'training-participant-test-role',
            'guard_name' => 'web',
        ]);

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }

    private function training(array $attributes = []): Training
    {
        return Training::query()->create(array_merge([
            'code' => 'TRN-' . fake()->unique()->numerify('####'),
            'name' => 'Leadership Training',
            'category' => 'Management',
            'description' => 'Training description.',
            'trainer' => 'Trainer Name',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'capacity' => 30,
            'status' => 'scheduled',
        ], $attributes));
    }

    private function employee(array $attributes = []): Employee
    {
        $department = Department::query()->create([
            'code' => 'DEP-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Technology',
            'description' => 'Technology Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = Position::query()->create([
            'code' => 'POS-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Software Engineer',
            'description' => 'Software Engineer Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        return Employee::query()->create(array_merge([
            'user_id' => null,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => null,
            'employee_number' => 'EMP-' . strtoupper(substr(uniqid(), -6)),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '08123456789',
            'address' => 'Jakarta',
            'join_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ], $attributes));
    }

    private function participant(
        Training $training,
        Employee $employee,
        array $attributes = [],
    ): TrainingParticipant {
        return TrainingParticipant::query()->create(array_merge([
            'training_id' => $training->id,
            'employee_id' => $employee->id,
            'status' => 'registered',
            'registered_at' => '2026-09-10 08:00:00',
        ], $attributes));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/training-participants')
            ->assertUnauthorized();
    }

    public function test_index_requires_permission(): void
    {
        $user = $this->userWithPermissions();

        $this->actingAs($user)
            ->getJson('/api/v1/training-participants')
            ->assertForbidden();
    }

    public function test_index_can_list_participants(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $training = $this->training();
        $employee = $this->employee();

        $this->participant($training, $employee);

        $this->actingAs($user)
            ->getJson('/api/v1/training-participants')
            ->assertOk()
            ->assertJsonPath('message', 'Daftar peserta training berhasil diambil.')
            ->assertJsonPath('data.0.training_id', $training->id)
            ->assertJsonPath('data.0.employee_id', $employee->id)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_index_can_filter_by_training(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $trainingA = $this->training(['code' => 'TRN-A001']);
        $trainingB = $this->training(['code' => 'TRN-B001']);
        $employee = $this->employee();

        $this->participant($trainingA, $employee);
        $this->participant($trainingB, $employee);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/training-participants?training_id={$trainingA->id}")
            ->assertOk();

        $response->assertJsonPath('meta.pagination.total', 1);
        $response->assertJsonPath('data.0.training_id', $trainingA->id);
    }

    public function test_index_can_filter_by_employee(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $trainingA = $this->training(['code' => 'TRN-A001']);
        $trainingB = $this->training(['code' => 'TRN-B001']);
        $employeeA = $this->employee(['employee_number' => 'EMP-A001']);
        $employeeB = $this->employee(['employee_number' => 'EMP-B001']);

        $this->participant($trainingA, $employeeA);
        $this->participant($trainingB, $employeeB);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/training-participants?employee_id={$employeeA->id}")
            ->assertOk();

        $response->assertJsonPath('meta.pagination.total', 1);
        $response->assertJsonPath('data.0.employee_id', $employeeA->id);
    }

    public function test_index_supports_pagination(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $training = $this->training();

        for ($i = 1; $i <= 3; $i++) {
            $employee = $this->employee();
            $this->participant($training, $employee);
        }

        $this->actingAs($user)
            ->getJson('/api/v1/training-participants?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2);
    }

    public function test_index_clamps_per_page_to_maximum_100(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);

        $this->actingAs($user)
            ->getJson('/api/v1/training-participants?per_page=999')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_index_clamps_per_page_to_minimum_1(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);

        $this->actingAs($user)
            ->getJson('/api/v1/training-participants?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 1);
    }

    public function test_show_can_display_participant(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->getJson("/api/v1/training-participants/{$participant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $participant->id)
            ->assertJsonPath('data.training_id', $training->id)
            ->assertJsonPath('data.employee_id', $employee->id);
    }

    public function test_show_returns_not_found_for_missing_participant(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);

        $this->actingAs($user)
            ->getJson('/api/v1/training-participants/999999')
            ->assertNotFound();
    }

    public function test_store_requires_permission(): void
    {
        $user = $this->userWithPermissions();
        $training = $this->training();
        $employee = $this->employee();

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [
                'training_id' => $training->id,
                'employee_id' => $employee->id,
            ])
            ->assertForbidden();
    }

    public function test_store_can_register_participant(): void
    {
        $user = $this->userWithPermissions(['training.participant.register']);
        $training = $this->training();
        $employee = $this->employee();

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [
                'training_id' => $training->id,
                'employee_id' => $employee->id,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Peserta berhasil didaftarkan ke training.')
            ->assertJsonPath('data.training_id', $training->id)
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.status', 'registered');

        $this->assertDatabaseHas('training_participants', [
            'training_id' => $training->id,
            'employee_id' => $employee->id,
            'status' => 'registered',
        ]);
    }

    public function test_store_requires_training_id_and_employee_id(): void
    {
        $user = $this->userWithPermissions(['training.participant.register']);

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'training_id',
                'employee_id',
            ]);
    }

    public function test_store_rejects_nonexistent_training(): void
    {
        $user = $this->userWithPermissions(['training.participant.register']);
        $employee = $this->employee();

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [
                'training_id' => 999999,
                'employee_id' => $employee->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['training_id']);
    }

    public function test_store_rejects_nonexistent_employee(): void
    {
        $user = $this->userWithPermissions(['training.participant.register']);
        $training = $this->training();

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [
                'training_id' => $training->id,
                'employee_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_store_rejects_duplicate_participant_in_same_training(): void
    {
        $user = $this->userWithPermissions(['training.participant.register']);
        $training = $this->training();
        $employee = $this->employee();

        $this->participant($training, $employee);

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [
                'training_id' => $training->id,
                'employee_id' => $employee->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_store_allows_same_employee_in_different_training(): void
    {
        $user = $this->userWithPermissions(['training.participant.register']);
        $trainingA = $this->training(['code' => 'TRN-A001']);
        $trainingB = $this->training(['code' => 'TRN-B001']);
        $employee = $this->employee();

        $this->participant($trainingA, $employee);

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [
                'training_id' => $trainingB->id,
                'employee_id' => $employee->id,
            ])
            ->assertCreated();

        $this->assertDatabaseCount('training_participants', 2);
    }

    public function test_store_validates_status_length(): void
    {
        $user = $this->userWithPermissions(['training.participant.register']);
        $training = $this->training();
        $employee = $this->employee();

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [
                'training_id' => $training->id,
                'employee_id' => $employee->id,
                'status' => str_repeat('x', 31),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_validates_registered_at(): void
    {
        $user = $this->userWithPermissions(['training.participant.register']);
        $training = $this->training();
        $employee = $this->employee();

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants', [
                'training_id' => $training->id,
                'employee_id' => $employee->id,
                'registered_at' => 'not-a-date',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['registered_at']);
    }

    public function test_update_requires_permission(): void
    {
        $user = $this->userWithPermissions();
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->putJson("/api/v1/training-participants/{$participant->id}", [
                'status' => 'completed',
            ])
            ->assertForbidden();
    }

    public function test_update_can_update_participant(): void
    {
        $user = $this->userWithPermissions(['training.participant.update']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->putJson("/api/v1/training-participants/{$participant->id}", [
                'status' => 'completed',
                'registered_at' => '2026-09-10 09:00:00',
                'completed_at' => '2026-09-12 16:00:00',
                'certificate_path' => 'certificates/training-1.pdf',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.certificate_path', 'certificates/training-1.pdf');

        $this->assertDatabaseHas('training_participants', [
            'id' => $participant->id,
            'status' => 'completed',
            'certificate_path' => 'certificates/training-1.pdf',
        ]);
    }

    public function test_update_supports_partial_update(): void
    {
        $user = $this->userWithPermissions(['training.participant.update']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->patchJson("/api/v1/training-participants/{$participant->id}", [
                'status' => 'completed',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.certificate_path', null);
    }

    public function test_update_validates_status_length(): void
    {
        $user = $this->userWithPermissions(['training.participant.update']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->patchJson("/api/v1/training-participants/{$participant->id}", [
                'status' => str_repeat('x', 31),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_validates_dates(): void
    {
        $user = $this->userWithPermissions(['training.participant.update']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->patchJson("/api/v1/training-participants/{$participant->id}", [
                'registered_at' => 'invalid',
                'completed_at' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'registered_at',
                'completed_at',
            ]);
    }

    public function test_update_returns_not_found_for_missing_participant(): void
    {
        $user = $this->userWithPermissions(['training.participant.update']);

        $this->actingAs($user)
            ->patchJson('/api/v1/training-participants/999999', [
                'status' => 'completed',
            ])
            ->assertNotFound();
    }

    public function test_evaluate_requires_permission(): void
    {
        $user = $this->userWithPermissions();
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->postJson("/api/v1/training-participants/{$participant->id}/evaluate", [
                'score' => 85,
            ])
            ->assertForbidden();
    }

    public function test_evaluate_can_save_score(): void
    {
        $user = $this->userWithPermissions(['training.evaluation.create']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->postJson("/api/v1/training-participants/{$participant->id}/evaluate", [
                'score' => 85.5,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Evaluasi peserta training berhasil disimpan.')
            ->assertJsonPath('data.score', '85.50');

        $this->assertDatabaseHas('training_participants', [
            'id' => $participant->id,
            'score' => 85.50,
        ]);
    }

    public function test_evaluate_rejects_score_below_zero(): void
    {
        $user = $this->userWithPermissions(['training.evaluation.create']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->postJson("/api/v1/training-participants/{$participant->id}/evaluate", [
                'score' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_evaluate_rejects_score_above_100(): void
    {
        $user = $this->userWithPermissions(['training.evaluation.create']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->postJson("/api/v1/training-participants/{$participant->id}/evaluate", [
                'score' => 101,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_evaluate_requires_score(): void
    {
        $user = $this->userWithPermissions(['training.evaluation.create']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->postJson("/api/v1/training-participants/{$participant->id}/evaluate", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_evaluate_returns_not_found_for_missing_participant(): void
    {
        $user = $this->userWithPermissions(['training.evaluation.create']);

        $this->actingAs($user)
            ->postJson('/api/v1/training-participants/999999/evaluate', [
                'score' => 85,
            ])
            ->assertNotFound();
    }

    public function test_history_requires_permission(): void
    {
        $user = $this->userWithPermissions();

        $this->actingAs($user)
            ->getJson('/api/v1/training-participants/history/1')
            ->assertForbidden();
    }

    public function test_history_can_display_employee_training_history(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $trainingA = $this->training(['code' => 'TRN-A001']);
        $trainingB = $this->training(['code' => 'TRN-B001']);
        $employee = $this->employee();

        $this->participant($trainingA, $employee, [
            'registered_at' => '2026-09-10 08:00:00',
        ]);

        $this->participant($trainingB, $employee, [
            'registered_at' => '2026-09-11 08:00:00',
        ]);

        $otherEmployee = $this->employee();
        $this->participant($trainingA, $otherEmployee);

        $this->actingAs($user)
            ->getJson("/api/v1/training-participants/history/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.employee_id', $employee->id)
            ->assertJsonPath('data.0.training.id', $trainingB->id);
    }

    public function test_history_supports_pagination(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $employee = $this->employee();

        for ($i = 1; $i <= 3; $i++) {
            $training = $this->training(['code' => "TRN-{$i}001"]);
            $this->participant($training, $employee);
        }

        $this->actingAs($user)
            ->getJson("/api/v1/training-participants/history/{$employee->id}?per_page=2")
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2);
    }

    public function test_history_returns_empty_for_employee_without_history(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $employee = $this->employee();

        $this->actingAs($user)
            ->getJson("/api/v1/training-participants/history/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_history_returns_empty_for_missing_employee(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);

        $this->actingAs($user)
            ->getJson('/api/v1/training-participants/history/999999')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_destroy_requires_permission(): void
    {
        $user = $this->userWithPermissions();
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->deleteJson("/api/v1/training-participants/{$participant->id}")
            ->assertForbidden();
    }

    public function test_destroy_can_delete_participant(): void
    {
        $user = $this->userWithPermissions(['training.participant.delete']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee);

        $this->actingAs($user)
            ->deleteJson("/api/v1/training-participants/{$participant->id}")
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Peserta berhasil dihapus dari training.');

        $this->assertDatabaseMissing('training_participants', [
            'id' => $participant->id,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_participant(): void
    {
        $user = $this->userWithPermissions(['training.participant.delete']);

        $this->actingAs($user)
            ->deleteJson('/api/v1/training-participants/999999')
            ->assertNotFound();
    }

    public function test_resource_returns_expected_fields(): void
    {
        $user = $this->userWithPermissions(['training.participant.view']);
        $training = $this->training();
        $employee = $this->employee();
        $participant = $this->participant($training, $employee, [
            'status' => 'completed',
            'score' => 87.50,
            'registered_at' => '2026-09-10 08:00:00',
            'completed_at' => '2026-09-12 16:00:00',
            'certificate_path' => 'certificates/training-1.pdf',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/training-participants/{$participant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $participant->id)
            ->assertJsonPath('data.training_id', $training->id)
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.score', '87.50')
            ->assertJsonPath('data.certificate_path', 'certificates/training-1.pdf')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'training_id',
                    'employee_id',
                    'status',
                    'score',
                    'registered_at',
                    'completed_at',
                    'certificate_path',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }
}
