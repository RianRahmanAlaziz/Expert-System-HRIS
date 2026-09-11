<?php

namespace Tests\Feature\Consultation;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExpertConsultationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPermission(string $permission): void
    {
        Permission::findOrCreate($permission, 'web');
    }

    private function createUserWithPermission(string $permission): User
    {
        $this->createPermission($permission);

        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function createEmployee(): Employee
    {
        $suffix = uniqid();

        $department = Department::query()->create([
            'code' => 'DEP-' . $suffix,
            'name' => 'Test Department ' . $suffix,
            'description' => 'Department for testing.',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = Position::query()->create([
            'code' => 'POS-' . $suffix,
            'name' => 'Test Position ' . $suffix,
            'description' => 'Position for testing.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);

        return app(EmployeeService::class)->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-' . $suffix,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => now()->subYears(30),
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => now()->subYears(2),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee joined the company.',
        ]);
    }

    private function createConsultation(
        Employee $employee,
        User $user,
        string $consultationType = 'promotion',
    ): ExpertConsultation {
        return ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => $consultationType,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_index(): void
    {
        $this->getJson('/api/v1/expert-consultations')
            ->assertUnauthorized();
    }

    public function test_user_without_view_permission_cannot_access_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/expert-consultations')
            ->assertForbidden();
    }

    public function test_user_can_list_expert_consultations(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        $consultation = $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->actingAs($user)
            ->getJson('/api/v1/expert-consultations')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $consultation->id,
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]);
    }

    public function test_index_uses_default_pagination(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        for ($i = 0; $i < 3; $i++) {
            $this->createConsultation(
                employee: $employee,
                user: $user,
            );
        }

        $this->actingAs($user)
            ->getJson('/api/v1/expert-consultations')
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                3,
            );
    }

    public function test_index_supports_custom_pagination(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        for ($i = 0; $i < 5; $i++) {
            $this->createConsultation(
                employee: $employee,
                user: $user,
            );
        }

        $this->actingAs($user)
            ->getJson('/api/v1/expert-consultations?per_page=2')
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                5,
            );
    }

    public function test_index_clamps_per_page_to_minimum_one(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->actingAs($user)
            ->getJson('/api/v1/expert-consultations?per_page=0')
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                1,
            );
    }

    public function test_index_clamps_per_page_to_maximum_one_hundred(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->actingAs($user)
            ->getJson('/api/v1/expert-consultations?per_page=200')
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                100,
            );
    }

    public function test_index_can_search_by_employee_number(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        $consultation = $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->actingAs($user)
            ->getJson(
                '/api/v1/expert-consultations?search='
                    . $employee->employee_number,
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $consultation->id,
            ]);
    }

    public function test_index_can_search_by_employee_name(): void
    {
        $user = $this->createUserWithPermission('expert_consultation.view');

        $employee = $this->createEmployee();

        $consultation = $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->actingAs($user)
            ->getJson(
                '/api/v1/expert-consultations?search=John',
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $consultation->id,
            ]);
    }

    public function test_index_ignores_empty_search(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        $consultation = $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->actingAs($user)
            ->getJson('/api/v1/expert-consultations?search=%20')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $consultation->id,
            ]);
    }

    public function test_index_can_filter_by_employee_id(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employeeA = $this->createEmployee();
        $employeeB = $this->createEmployee();

        $consultationA = $this->createConsultation(
            employee: $employeeA,
            user: $user,
        );

        $consultationB = $this->createConsultation(
            employee: $employeeB,
            user: $user,
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/expert-consultations?employee_id='
                    . $employeeA->id,
            )
            ->assertOk();

        $response->assertJsonFragment([
            'id' => $consultationA->id,
        ]);

        $response->assertJsonMissing([
            'id' => $consultationB->id,
        ]);
    }

    public function test_index_can_filter_by_consultation_type(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        $promotion = $this->createConsultation(
            employee: $employee,
            user: $user,
            consultationType: 'promotion',
        );

        $training = $this->createConsultation(
            employee: $employee,
            user: $user,
            consultationType: 'training',
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/v1/expert-consultations'
                    . '?consultation_type=promotion',
            )
            ->assertOk();

        $response->assertJsonFragment([
            'id' => $promotion->id,
        ]);

        $response->assertJsonMissing([
            'id' => $training->id,
        ]);
    }

    public function test_user_can_show_expert_consultation(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        $consultation = $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->actingAs($user)
            ->getJson(
                "/api/v1/expert-consultations/{$consultation->id}",
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $consultation->id,
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ]);
    }

    public function test_show_returns_not_found_for_invalid_id(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $this->actingAs($user)
            ->getJson('/api/v1/expert-consultations/999999')
            ->assertNotFound();
    }

    public function test_user_can_create_expert_consultation(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.create',
        );

        $employee = $this->createEmployee();

        $payload = [
            'employee_id' => $employee->id,
            'consultation_type' => 'promotion',
        ];

        $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', $payload)
            ->assertCreated()
            ->assertJsonFragment([
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
                'status' => 'completed',
            ]);

        $this->assertDatabaseHas('expert_consultations', [
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'consultation_type' => 'promotion',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('consultation_results', [
            'expert_consultation_id' =>
            ExpertConsultation::query()->latest('id')->value('id'),
        ]);
    }

    public function test_store_requires_employee_id(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.create',
        );

        $response = $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', [
                'consultation_type' => 'promotion',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_id',
            ]);
    }

    public function test_store_requires_consultation_type(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.create',
        );

        $employee = $this->createEmployee();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', [
                'employee_id' => $employee->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'consultation_type',
            ]);
    }

    public function test_store_rejects_non_existing_employee(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.create',
        );

        $response = $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', [
                'employee_id' => 999999,
                'consultation_type' => 'promotion',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_id',
            ]);
    }

    public function test_store_rejects_non_integer_employee_id(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.create',
        );

        $response = $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', [
                'employee_id' => 'invalid',
                'consultation_type' => 'promotion',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'employee_id',
            ]);
    }

    public function test_store_rejects_non_string_consultation_type(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.create',
        );

        $employee = $this->createEmployee();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', [
                'employee_id' => $employee->id,
                'consultation_type' => 123,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'consultation_type',
            ]);
    }

    public function test_store_rejects_consultation_type_above_maximum_length(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.create',
        );

        $employee = $this->createEmployee();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', [
                'employee_id' => $employee->id,
                'consultation_type' => str_repeat('a', 101),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'consultation_type',
            ]);
    }

    public function test_user_without_create_permission_cannot_create(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee();

        $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ])
            ->assertForbidden();
    }

    public function test_resource_contains_employee_data(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.view',
        );

        $employee = $this->createEmployee();

        $consultation = $this->createConsultation(
            employee: $employee,
            user: $user,
        );

        $this->actingAs($user)
            ->getJson(
                "/api/v1/expert-consultations/{$consultation->id}",
            )
            ->assertOk()
            ->assertJsonPath(
                'data.employee.id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.employee.employee_number',
                $employee->employee_number,
            )
            ->assertJsonPath(
                'data.employee.first_name',
                'John',
            )
            ->assertJsonPath(
                'data.employee.last_name',
                'Doe',
            );
    }

    public function test_resource_contains_consultation_result(): void
    {
        $user = $this->createUserWithPermission(
            'expert_consultation.create',
        );

        $employee = $this->createEmployee();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/expert-consultations', [
                'employee_id' => $employee->id,
                'consultation_type' => 'promotion',
            ])
            ->assertCreated();

        $response
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'employee_id',
                    'employee',
                    'user_id',
                    'consultation_type',
                    'status',
                    'started_at',
                    'completed_at',
                    'result' => [
                        'id',
                        'expert_consultation_id',
                        'recommendation',
                        'score',
                        'confidence',
                        'reason',
                        'input_snapshot',
                        'matched_rules',
                        'suggested_actions',
                        'created_at',
                        'updated_at',
                    ],
                    'created_at',
                    'updated_at',
                ],
            ]);
    }
}
