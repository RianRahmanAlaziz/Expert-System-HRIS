<?php

namespace Tests\Feature\ExpertSystem;

use App\Models\ConsultationResult;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpertSystemReportControllerTest extends TestCase
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

    private function createConsultation(
        Employee $employee,
        string $consultationType = 'promotion',
        string $recommendation = 'Recommended',
    ): ExpertConsultation {
        $consultation = ExpertConsultation::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $this->user->id,
            'consultation_type' => $consultationType,
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        ConsultationResult::query()->create([
            'expert_consultation_id' => $consultation->id,
            'recommendation' => $recommendation,
            'score' => 85,
            'confidence' => 90,
            'reason' => 'Test consultation result.',
            'input_snapshot' => [
                'employee' => [
                    'id' => $employee->id,
                ],
            ],
            'matched_rules' => [],
            'suggested_actions' => [],
        ]);

        return $consultation;
    }

    public function test_guest_cannot_access_expert_system_report(): void
    {
        $response = $this->getJson(
            '/api/v1/reports/expert-system',
        );

        $response->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_access_expert_system_report(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/reports/expert-system');

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_retrieve_expert_system_report(): void
    {
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/expert-system');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_consultations',
                        'completed_consultations',
                        'processing_consultations',
                        'average_score',
                        'average_confidence',
                    ],
                    'by_recommendation',
                    'by_consultation_type',
                    'consultations',
                ],
            ]);
    }

    public function test_report_contains_consultation_result(): void
    {
        $employee = $this->createEmployee();

        $consultation = $this->createConsultation(
            employee: $employee,
            consultationType: 'promotion',
            recommendation: 'Recommended',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/expert-system');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.consultations.0.id',
                $consultation->id,
            )
            ->assertJsonPath(
                'data.consultations.0.employee.id',
                $employee->id,
            )
            ->assertJsonPath(
                'data.consultations.0.consultation_type',
                'promotion',
            )
            ->assertJsonPath(
                'data.consultations.0.result.recommendation',
                'Recommended',
            )
            ->assertJsonPath(
                'data.consultations.0.result.reason',
                'Test consultation result.',
            );
    }

    public function test_report_can_filter_by_employee(): void
    {
        $employee = $this->createEmployee();
        $otherEmployee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
        );

        $this->createConsultation(
            employee: $otherEmployee,
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/expert-system?employee_id='
                    . $employee->id,
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_consultations',
                1,
            )
            ->assertJsonPath(
                'data.consultations.0.employee.id',
                $employee->id,
            );
    }

    public function test_report_can_filter_by_consultation_type(): void
    {
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            consultationType: 'promotion',
        );

        $this->createConsultation(
            employee: $employee,
            consultationType: 'training',
            recommendation: 'Consider',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/expert-system'
                    . '?consultation_type=promotion',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_consultations',
                1,
            )
            ->assertJsonPath(
                'data.consultations.0.consultation_type',
                'promotion',
            );
    }

    public function test_report_can_filter_by_recommendation(): void
    {
        $employee = $this->createEmployee();

        $this->createConsultation(
            employee: $employee,
            recommendation: 'Recommended',
        );

        $this->createConsultation(
            employee: $employee,
            consultationType: 'training',
            recommendation: 'Consider',
        );

        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/expert-system'
                    . '?recommendation=Recommended',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_consultations',
                1,
            )
            ->assertJsonPath(
                'data.consultations.0.result.recommendation',
                'Recommended',
            );
    }

    public function test_invalid_employee_id_is_rejected(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/expert-system'
                    . '?employee_id=999999',
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'employee_id',
        ]);
    }

    public function test_invalid_consultation_type_is_rejected(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/expert-system'
                    . '?consultation_type='
                    . str_repeat('a', 101),
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'consultation_type',
        ]);
    }

    public function test_invalid_recommendation_is_rejected(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson(
                '/api/v1/reports/expert-system'
                    . '?recommendation='
                    . str_repeat('a', 101),
            );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'recommendation',
        ]);
    }

    public function test_empty_report_returns_zero_summary(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/reports/expert-system');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_consultations',
                0,
            )
            ->assertJsonPath(
                'data.summary.completed_consultations',
                0,
            )
            ->assertJsonPath(
                'data.summary.processing_consultations',
                0,
            )
            ->assertJsonPath(
                'data.consultations',
                [],
            );
    }
}
