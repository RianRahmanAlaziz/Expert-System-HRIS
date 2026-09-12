<?php

namespace Tests\Feature\Recommendation;

use App\Models\ConsultationResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\Position;
use App\Models\Recommendation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();

        $this->user->assignRole('hr-admin');
    }

    private function createDepartment(
        string $code = 'HR',
    ): Department {
        return Department::query()->create([
            'code' => $code,
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(
        string $code = 'STAFF',
    ): Position {
        return Position::query()->create([
            'code' => $code,
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        string $employeeNumber = 'EMP-001',
    ): Employee {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        return Employee::query()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employee_number' => $employeeNumber,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-15',
            'phone' => '081234567890',
            'address' => 'Jakarta',
            'join_date' => '2026-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }

    private function createConsultation(
        ?Employee $employee = null,
    ): ExpertConsultation {
        $employee ??= $this->createEmployee();

        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $this->user->id,
            'consultation_type' => 'promotion',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        ConsultationResult::query()->create([
            'expert_consultation_id' => $consultation->id,
            'recommendation' => 'Recommended',
            'score' => 85,
            'confidence' => 90,
            'reason' => 'Employee memenuhi kriteria promotion.',
            'input_snapshot' => [
                'performance' => 90,
                'competency' => 85,
                'attendance' => 95,
                'experience' => 4,
            ],
            'matched_rules' => [],
            'suggested_actions' => [],
        ]);

        return $consultation;
    }

    private function createRecommendation(
        ?Employee $employee = null,
    ): Recommendation {
        $employee ??= $this->createEmployee();

        return Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'title' => 'Rekomendasi Promosi',
            'description' => 'Employee direkomendasikan untuk promosi.',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);
    }

    public function test_unauthenticated_user_cannot_access_recommendations(): void
    {
        $response = $this->getJson('/api/v1/recommendations');

        $response->assertUnauthorized();
    }

    public function test_user_can_get_recommendation_index(): void
    {
        $this->actingAs($this->user);

        $this->createRecommendation();

        $response = $this->getJson(
            '/api/v1/recommendations',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.type',
                'promotion',
            );
    }

    public function test_user_can_filter_recommendations_by_type(): void
    {
        $this->actingAs($this->user);

        $employee = $this->createEmployee();

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'title' => 'Promotion',
            'priority' => 'high',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'training',
            'title' => 'Training',
            'priority' => 'medium',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/v1/recommendations?type=promotion',
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.type',
                'promotion',
            );
    }

    public function test_user_can_filter_recommendations_by_status(): void
    {
        $this->actingAs($this->user);

        $employee = $this->createEmployee();

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'promotion',
            'title' => 'Approved Promotion',
            'priority' => 'high',
            'status' => 'approved',
            'recommended_at' => now(),
        ]);

        Recommendation::query()->create([
            'employee_id' => $employee->id,
            'type' => 'training',
            'title' => 'Pending Training',
            'priority' => 'medium',
            'status' => 'pending',
            'recommended_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/v1/recommendations?status=approved',
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.status',
                'approved',
            );
    }

    public function test_user_can_create_recommendation_from_consultation(): void
    {
        $this->actingAs($this->user);

        $consultation = $this->createConsultation();

        $response = $this->postJson(
            '/api/v1/recommendations',
            [
                'expert_consultation_id' => $consultation->id,
                'type' => 'promotion',
                'title' => 'Rekomendasi Promosi',
                'description' => 'Employee direkomendasikan untuk promosi.',
                'priority' => 'high',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.type',
                'promotion',
            )
            ->assertJsonPath(
                'data.status',
                'pending',
            );

        $this->assertDatabaseHas('recommendations', [
            'employee_id' => $consultation->employee_id,
            'expert_consultation_id' => $consultation->id,
            'type' => 'promotion',
            'status' => 'pending',
        ]);
    }

    public function test_create_recommendation_requires_consultation(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/recommendations',
            [
                'type' => 'promotion',
                'title' => 'Rekomendasi Promosi',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'expert_consultation_id',
            ]);
    }

    public function test_create_recommendation_validates_type(): void
    {
        $this->actingAs($this->user);

        $consultation = $this->createConsultation();

        $response = $this->postJson(
            '/api/v1/recommendations',
            [
                'expert_consultation_id' => $consultation->id,
                'type' => 'invalid_type',
                'title' => 'Recommendation',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
            ]);
    }

    public function test_create_recommendation_validates_title(): void
    {
        $this->actingAs($this->user);

        $consultation = $this->createConsultation();

        $response = $this->postJson(
            '/api/v1/recommendations',
            [
                'expert_consultation_id' => $consultation->id,
                'type' => 'promotion',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
            ]);
    }

    public function test_user_can_show_recommendation(): void
    {
        $this->actingAs($this->user);

        $recommendation = $this->createRecommendation();

        $response = $this->getJson(
            "/api/v1/recommendations/{$recommendation->id}",
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $recommendation->id,
            )
            ->assertJsonPath(
                'data.type',
                'promotion',
            );
    }

    public function test_show_recommendation_returns_not_found_for_invalid_id(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/recommendations/999999',
        );

        $response->assertNotFound();
    }

    public function test_user_can_update_recommendation_status(): void
    {
        $this->actingAs($this->user);

        $recommendation = $this->createRecommendation();

        $response = $this->patchJson(
            "/api/v1/recommendations/{$recommendation->id}/status",
            [
                'status' => 'approved',
                'notes' => 'Disetujui oleh HR.',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'approved',
            );

        $this->assertDatabaseHas('recommendations', [
            'id' => $recommendation->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('recommendation_histories', [
            'recommendation_id' => $recommendation->id,
            'user_id' => $this->user->id,
            'old_status' => 'pending',
            'new_status' => 'approved',
            'notes' => 'Disetujui oleh HR.',
        ]);
    }

    public function test_update_status_requires_valid_status(): void
    {
        $this->actingAs($this->user);

        $recommendation = $this->createRecommendation();

        $response = $this->patchJson(
            "/api/v1/recommendations/{$recommendation->id}/status",
            [
                'status' => 'invalid_status',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_update_status_requires_status(): void
    {
        $this->actingAs($this->user);

        $recommendation = $this->createRecommendation();

        $response = $this->patchJson(
            "/api/v1/recommendations/{$recommendation->id}/status",
            [],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_update_status_returns_not_found_for_invalid_id(): void
    {
        $this->actingAs($this->user);

        $response = $this->patchJson(
            '/api/v1/recommendations/999999/status',
            [
                'status' => 'approved',
            ],
        );

        $response->assertNotFound();
    }
}
