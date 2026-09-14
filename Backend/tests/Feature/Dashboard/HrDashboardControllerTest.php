<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();

        $this->user->assignRole('hr-admin');
    }

    public function test_hr_dashboard_can_be_retrieved(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/dashboard/hr');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'employee' => [
                        'total',
                        'active',
                        'inactive',
                    ],
                    'attendance' => [
                        'date',
                        'total',
                        'present',
                        'late',
                        'absent',
                    ],
                    'leave' => [
                        'pending',
                    ],
                    'performance' => [
                        'average_score',
                    ],
                    'competency' => [
                        'gap_count',
                    ],
                    'employee_turnover' => [
                        'count',
                        'rate',
                    ],
                    'recommendation' => [
                        'training',
                        'promotion',
                    ],
                ],
            ]);
    }

    public function test_user_without_dashboard_permission_cannot_retrieve_hr_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/dashboard/hr');

        $response->assertForbidden();
    }

    public function test_hr_dashboard_returns_zero_statistics_when_no_data_exists(): void
    {
        $response = $this
            ->actingAs($this->user)
            ->getJson('/api/v1/dashboard/hr');

        $response
            ->assertOk()
            ->assertJsonPath('data.employee.total', 0)
            ->assertJsonPath('data.employee.active', 0)
            ->assertJsonPath('data.employee.inactive', 0)
            ->assertJsonPath('data.attendance.total', 0)
            ->assertJsonPath('data.attendance.present', 0)
            ->assertJsonPath('data.attendance.late', 0)
            ->assertJsonPath('data.attendance.absent', 0)
            ->assertJsonPath('data.leave.pending', 0)
            ->assertJsonPath('data.performance.average_score', 0)
            ->assertJsonPath('data.competency.gap_count', 0)
            ->assertJsonPath('data.employee_turnover.count', 0)
            ->assertJsonPath('data.employee_turnover.rate', 0)
            ->assertJsonPath('data.recommendation.training', 0)
            ->assertJsonPath('data.recommendation.promotion', 0);
    }
}
