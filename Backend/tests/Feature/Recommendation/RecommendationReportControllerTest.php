<?php

namespace Tests\Feature\Recommendation;

use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\Position;
use App\Models\Recommendation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationReportControllerTest extends TestCase
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

    private function createEmployee(): Employee
    {
        $suffix = uniqid();

        $department = Department::query()->create([
            'code' => 'DEP-' . $suffix,
            'name' => 'Human Resources ' . $suffix,
            'description' => 'Human Resources Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = Position::query()->create([
            'code' => 'POS-' . $suffix,
            'name' => 'HR Staff ' . $suffix,
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);

        return Employee::query()->create([
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
        ]);
    }

    private function createRecommendation(
        Employee $employee,
        string $type = 'promotion',
        string $status = 'pending',
        string $priority = 'medium',
    ): Recommendation {
        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $this->user->id,
            'consultation_type' => $type,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return Recommendation::query()->create([
            'employee_id' => $employee->id,
            'expert_consultation_id' => $consultation->id,
            'type' => $type,
            'title' => 'Test Recommendation',
            'description' => 'Test recommendation description.',
            'priority' => $priority,
            'status' => $status,
            'recommended_at' => now(),
        ]);
    }

    public function test_guest_cannot_access_recommendation_report(): void
    {
        $response = $this->getJson(
            '/api/v1/reports/recommendations',
        );

        $response->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_access_recommendation_report(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/reports/recommendations');

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_retrieve_recommendation_report(): void
    {
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/recommendations');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_recommendations',
                        'pending_recommendations',
                        'approved_recommendations',
                        'rejected_recommendations',
                        'implemented_recommendations',
                    ],
                    'by_type',
                    'by_status',
                    'by_priority',
                    'recommendations',
                ],
            ]);
    }

    public function test_report_contains_recommendation_data(): void
    {
        $employee = $this->createEmployee();

        $recommendation = $this->createRecommendation(
            employee: $employee,
            type: 'promotion',
            status: 'approved',
            priority: 'high',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/recommendations');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.recommendations.0.id',
                $recommendation->id,
            )
            ->assertJsonPath(
                'data.recommendations.0.employee.id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.recommendations.0.type',
                'promotion',
            )
            ->assertJsonPath(
                'data.recommendations.0.status',
                'approved',
            )
            ->assertJsonPath(
                'data.recommendations.0.priority',
                'high',
            );
    }

    public function test_report_can_filter_by_employee(): void
    {
        $employee = $this->createEmployee();
        $otherEmployee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
        );

        $this->createRecommendation(
            employee: $otherEmployee,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/recommendations?employee_id='
                    . $employee->id,
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_recommendations',
                1,
            )
            ->assertJsonPath(
                'data.recommendations.0.employee.id',
                $employee->id,
            );
    }

    public function test_report_can_filter_by_type(): void
    {
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            type: 'promotion',
        );

        $this->createRecommendation(
            employee: $employee,
            type: 'training',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/recommendations?type=promotion',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_recommendations',
                1,
            )
            ->assertJsonPath(
                'data.recommendations.0.type',
                'promotion',
            );
    }

    public function test_report_can_filter_by_status(): void
    {
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            status: 'pending',
        );

        $this->createRecommendation(
            employee: $employee,
            status: 'approved',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/recommendations?status=approved',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_recommendations',
                1,
            )
            ->assertJsonPath(
                'data.recommendations.0.status',
                'approved',
            );
    }

    public function test_report_can_filter_by_priority(): void
    {
        $employee = $this->createEmployee();

        $this->createRecommendation(
            employee: $employee,
            priority: 'high',
        );

        $this->createRecommendation(
            employee: $employee,
            priority: 'low',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/recommendations?priority=high',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_recommendations',
                1,
            )
            ->assertJsonPath(
                'data.recommendations.0.priority',
                'high',
            );
    }

    public function test_invalid_employee_id_is_rejected(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/recommendations'
                    . '?employee_id=999999',
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'employee_id',
        ]);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/recommendations'
                    . '?type=invalid_type',
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'type',
        ]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/recommendations'
                    . '?status=invalid_status',
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'status',
        ]);
    }

    public function test_empty_report_returns_zero_summary(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/recommendations');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_recommendations',
                0,
            )
            ->assertJsonPath(
                'data.summary.pending_recommendations',
                0,
            )
            ->assertJsonPath(
                'data.summary.approved_recommendations',
                0,
            )
            ->assertJsonPath(
                'data.summary.rejected_recommendations',
                0,
            )
            ->assertJsonPath(
                'data.summary.implemented_recommendations',
                0,
            )
            ->assertJsonPath(
                'data.recommendations',
                [],
            );
    }
}
