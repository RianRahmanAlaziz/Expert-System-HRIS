<?php

namespace Tests\Feature\Competency;

use App\Models\Competency;
use App\Models\CompetencyLevel;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCompetency;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeCompetencyControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        foreach (
            [
                'employee-competency.view',
                'employee-competency.create',
                'employee-competency.update',
                'employee-competency.delete',
            ] as $permission
        ) {
            Permission::findOrCreate(
                $permission,
                'web',
            );
        }
    }

    private function givePermission(string $permission): void
    {
        $this->user->givePermissionTo($permission);
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => fake()->unique()->numerify('DEP####'),
            'name' => 'Test Department',
            'description' => 'Department for testing.',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => fake()->unique()->numerify('POS####'),
            'name' => 'Test Position',
            'description' => 'Position for testing.',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        ?User $user = null,
        ?Employee $manager = null,
        ?string $employeeNumber = null,
        ?string $firstName = null,
        ?string $lastName = null,
    ): Employee {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        return Employee::query()->create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $manager?->id,
            'employee_number' => $employeeNumber
                ?? fake()->unique()->numerify('EMP####'),
            'first_name' => $firstName ?? 'Test',
            'last_name' => $lastName ?? 'Employee',
            'gender' => 'male',
            'join_date' => '2026-01-01',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee created for testing.',
        ]);
    }

    private function createCompetency(
        string $code = 'COMP001',
        string $name = 'Leadership',
        string $category = 'Behavioral',
    ): Competency {
        return Competency::query()->create([
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'description' => 'Test competency.',
            'status' => 'active',
        ]);
    }

    private function createCompetencyLevel(
        ?int $level = null,
        string $name = 'Intermediate',
    ): CompetencyLevel {
        $level ??= fake()->unique()->numberBetween(1, 9999);

        return CompetencyLevel::query()->create([
            'level' => $level,
            'name' => $name,
            'description' => 'Test competency level.',
        ]);
    }

    private function createEmployeeCompetency(
        ?Employee $employee = null,
        ?Competency $competency = null,
        ?CompetencyLevel $competencyLevel = null,
        array $attributes = [],
    ): EmployeeCompetency {
        $employee ??= $this->createEmployee();
        $competency ??= $this->createCompetency(
            code: fake()->unique()->numerify('COMP####'),
        );
        $competencyLevel ??= $this->createCompetencyLevel(
            name: 'Level ' . fake()->unique()->numerify('####'),
        );

        return EmployeeCompetency::query()->create([
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $competencyLevel->id,
            'score' => null,
            'assessed_at' => null,
            'assessed_by' => null,
            'notes' => null,
            ...$attributes,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/employee-competencies');

        $response->assertUnauthorized();
    }

    public function test_requires_view_permission_for_index(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/employee-competencies');

        $response->assertForbidden();
    }

    public function test_requires_view_permission_for_show(): void
    {
        $employeeCompetency = $this->createEmployeeCompetency();

        $this->actingAs($this->user);

        $response = $this->getJson(
            "/api/v1/employee-competencies/{$employeeCompetency->id}",
        );

        $response->assertForbidden();
    }

    public function test_requires_create_permission(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [],
        );

        $response->assertForbidden();
    }

    public function test_requires_update_permission(): void
    {
        $employeeCompetency = $this->createEmployeeCompetency();

        $this->actingAs($this->user);

        $response = $this->putJson(
            "/api/v1/employee-competencies/{$employeeCompetency->id}",
            [],
        );

        $response->assertForbidden();
    }

    public function test_requires_delete_permission(): void
    {
        $employeeCompetency = $this->createEmployeeCompetency();

        $this->actingAs($this->user);

        $response = $this->deleteJson(
            "/api/v1/employee-competencies/{$employeeCompetency->id}",
        );

        $response->assertForbidden();
    }

    public function test_can_list_employee_competencies(): void
    {
        $this->givePermission('employee-competency.view');

        $employee1 = $this->createEmployee(
            employeeNumber: 'EMP001',
            firstName: 'John',
            lastName: 'Doe',
        );

        $employee2 = $this->createEmployee(
            employeeNumber: 'EMP002',
            firstName: 'Jane',
            lastName: 'Smith',
        );

        $competency1 = $this->createCompetency(
            code: 'COMP001',
            name: 'Leadership',
            category: 'Behavioral',
        );

        $competency2 = $this->createCompetency(
            code: 'COMP002',
            name: 'Communication',
            category: 'Behavioral',
        );

        $level = $this->createCompetencyLevel();

        $this->createEmployeeCompetency(
            employee: $employee1,
            competency: $competency1,
            competencyLevel: $level,
        );

        $this->createEmployeeCompetency(
            employee: $employee2,
            competency: $competency2,
            competencyLevel: $level,
        );

        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/employee-competencies');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonFragment([
                'id' => EmployeeCompetency::query()
                    ->where('employee_id', $employee1->id)
                    ->where('competency_id', $competency1->id)
                    ->value('id'),
            ])
            ->assertJsonFragment([
                'id' => EmployeeCompetency::query()
                    ->where('employee_id', $employee2->id)
                    ->where('competency_id', $competency2->id)
                    ->value('id'),
            ]);
    }

    public function test_uses_default_pagination(): void
    {
        $this->givePermission('employee-competency.view');

        $employee = $this->createEmployee();

        for ($i = 1; $i <= 16; $i++) {
            $this->createEmployeeCompetency(
                employee: $employee,
                competency: $this->createCompetency(
                    code: "COMP{$i}",
                    name: "Competency {$i}",
                ),
                competencyLevel: $this->createCompetencyLevel(
                    name: "Level {$i}",
                ),
            );
        }

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                15,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                16,
            );
    }

    public function test_can_paginate_employee_competencies(): void
    {
        $this->givePermission('employee-competency.view');

        $employee = $this->createEmployee();

        for ($i = 1; $i <= 5; $i++) {
            $this->createEmployeeCompetency(
                employee: $employee,
                competency: $this->createCompetency(
                    code: "COMP{$i}",
                    name: "Competency {$i}",
                ),
                competencyLevel: $this->createCompetencyLevel(
                    name: "Level {$i}",
                ),
            );
        }

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?per_page=2',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                2,
            )
            ->assertJsonPath(
                'meta.pagination.total',
                5,
            )
            ->assertJsonPath(
                'meta.pagination.last_page',
                3,
            );
    }

    public function test_per_page_zero_is_limited_to_one(): void
    {
        $this->givePermission('employee-competency.view');

        $employee = $this->createEmployee();

        for ($i = 1; $i <= 2; $i++) {
            $this->createEmployeeCompetency(
                employee: $employee,
                competency: $this->createCompetency(
                    code: "COMP{$i}",
                    name: "Competency {$i}",
                ),
                competencyLevel: $this->createCompetencyLevel(
                    name: "Level {$i}",
                ),
            );
        }

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?per_page=0',
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.pagination.per_page',
                1,
            );
    }

    public function test_per_page_above_100_is_limited_to_100(): void
    {
        $this->givePermission('employee-competency.view');

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?per_page=101',
        );

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_can_search_by_employee_number(): void
    {
        $this->givePermission('employee-competency.view');

        $employee = $this->createEmployee(
            employeeNumber: 'EMP-SEARCH',
        );

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $employeeCompetency = $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competency,
            competencyLevel: $level,
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?search=EMP-SEARCH',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $employeeCompetency->id,
            ]);
    }

    public function test_can_search_by_employee_name(): void
    {
        $this->givePermission('employee-competency.view');

        $employee = $this->createEmployee(
            firstName: 'UniqueFirst',
            lastName: 'UniqueLast',
        );

        $employeeCompetency = $this->createEmployeeCompetency(
            employee: $employee,
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?search=UniqueFirst',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $employeeCompetency->id,
            ]);
    }

    public function test_can_search_by_competency_code(): void
    {
        $this->givePermission('employee-competency.view');

        $competency = $this->createCompetency(
            code: 'COMP-SEARCH',
        );

        $employeeCompetency = $this->createEmployeeCompetency(
            competency: $competency,
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?search=COMP-SEARCH',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $employeeCompetency->id,
            ]);
    }

    public function test_can_search_by_competency_name(): void
    {
        $this->givePermission('employee-competency.view');

        $competency = $this->createCompetency(
            name: 'Unique Competency Name',
        );

        $employeeCompetency = $this->createEmployeeCompetency(
            competency: $competency,
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?search=Unique%20Competency%20Name',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $employeeCompetency->id,
            ]);
    }

    public function test_can_search_by_competency_category(): void
    {
        $this->givePermission('employee-competency.view');

        $competency = $this->createCompetency(
            category: 'Unique Category',
        );

        $employeeCompetency = $this->createEmployeeCompetency(
            competency: $competency,
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?search=Unique%20Category',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $employeeCompetency->id,
            ]);
    }

    public function test_can_search_by_competency_level(): void
    {
        $this->givePermission('employee-competency.view');

        $level = $this->createCompetencyLevel(
            name: 'Unique Level',
        );

        $employeeCompetency = $this->createEmployeeCompetency(
            competencyLevel: $level,
        );

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?search=Unique%20Level',
        );

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $employeeCompetency->id,
            ]);
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        $this->givePermission('employee-competency.view');

        $this->createEmployeeCompetency();

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies?search=NOT-FOUND',
        );

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_can_show_employee_competency(): void
    {
        $this->givePermission('employee-competency.view');

        $employeeCompetency = $this->createEmployeeCompetency();

        $this->actingAs($this->user);

        $response = $this->getJson(
            "/api/v1/employee-competencies/{$employeeCompetency->id}",
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $employeeCompetency->id)
            ->assertJsonPath('data.employee_id', $employeeCompetency->employee_id)
            ->assertJsonPath('data.competency_id', $employeeCompetency->competency_id)
            ->assertJsonPath(
                'data.competency_level_id',
                $employeeCompetency->competency_level_id,
            );
    }

    public function test_returns_404_for_unknown_employee_competency(): void
    {
        $this->givePermission('employee-competency.view');

        $this->actingAs($this->user);

        $response = $this->getJson(
            '/api/v1/employee-competencies/999999',
        );

        $response->assertNotFound();
    }

    public function test_can_create_employee_competency(): void
    {
        $this->givePermission('employee-competency.create');

        $employee = $this->createEmployee();
        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $payload = [
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'competency_level_id' => $level->id,
            'score' => 85.50,
            'assessed_at' => '2026-09-09',
            'assessed_by' => $this->user->id,
            'notes' => 'Assessment test',
        ];

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            $payload,
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.competency_id', $competency->id)
            ->assertJsonPath(
                'data.competency_level_id',
                $level->id,
            );

        $this->assertDatabaseHas(
            'employee_competencies',
            [
                'employee_id' => $employee->id,
                'competency_id' => $competency->id,
                'competency_level_id' => $level->id,
                'score' => 85.50,
                'assessed_by' => $this->user->id,
                'notes' => 'Assessment test',
            ],
        );

        $this->assertDatabaseHas(
            'employee_competencies',
            [
                'id' => $response->json('data.id'),
                'assessed_at' => '2026-09-09 00:00:00',
            ],
        );
    }

    public function test_store_requires_employee_id(): void
    {
        $this->givePermission('employee-competency.create');

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [
                'competency_id' => $competency->id,
                'competency_level_id' => $level->id,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_store_requires_competency_id(): void
    {
        $this->givePermission('employee-competency.create');

        $employee = $this->createEmployee();
        $level = $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [
                'employee_id' => $employee->id,
                'competency_level_id' => $level->id,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['competency_id']);
    }

    public function test_store_requires_competency_level_id(): void
    {
        $this->givePermission('employee-competency.create');

        $employee = $this->createEmployee();
        $competency = $this->createCompetency();

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [
                'employee_id' => $employee->id,
                'competency_id' => $competency->id,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['competency_level_id']);
    }

    public function test_store_rejects_non_existing_employee(): void
    {
        $this->givePermission('employee-competency.create');

        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [
                'employee_id' => 999999,
                'competency_id' => $competency->id,
                'competency_level_id' => $level->id,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_store_rejects_non_existing_competency(): void
    {
        $this->givePermission('employee-competency.create');

        $employee = $this->createEmployee();
        $level = $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [
                'employee_id' => $employee->id,
                'competency_id' => 999999,
                'competency_level_id' => $level->id,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['competency_id']);
    }

    public function test_store_rejects_non_existing_competency_level(): void
    {
        $this->givePermission('employee-competency.create');

        $employee = $this->createEmployee();
        $competency = $this->createCompetency();

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [
                'employee_id' => $employee->id,
                'competency_id' => $competency->id,
                'competency_level_id' => 999999,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['competency_level_id']);
    }

    public function test_store_rejects_duplicate_employee_competency(): void
    {
        $this->givePermission('employee-competency.create');

        $employee = $this->createEmployee();
        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competency,
            competencyLevel: $level,
        );

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [
                'employee_id' => $employee->id,
                'competency_id' => $competency->id,
                'competency_level_id' => $level->id,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['competency_id']);
    }

    public function test_store_rejects_score_above_maximum(): void
    {
        $this->givePermission('employee-competency.create');

        $employee = $this->createEmployee();
        $competency = $this->createCompetency();
        $level = $this->createCompetencyLevel();

        $this->actingAs($this->user);

        $response = $this->postJson(
            '/api/v1/employee-competencies',
            [
                'employee_id' => $employee->id,
                'competency_id' => $competency->id,
                'competency_level_id' => $level->id,
                'score' => 1000,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['score']);
    }

    public function test_can_update_employee_competency(): void
    {
        $this->givePermission('employee-competency.update');

        $employeeCompetency = $this->createEmployeeCompetency();

        $newLevel = $this->createCompetencyLevel(
            name: 'Advanced',
        );

        $this->actingAs($this->user);

        $response = $this->putJson(
            "/api/v1/employee-competencies/{$employeeCompetency->id}",
            [
                'competency_level_id' => $newLevel->id,
                'score' => 95.00,
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.competency_level_id',
                $newLevel->id,
            )
            ->assertJsonPath('data.score', '95.00');
    }

    public function test_can_partially_update_employee_competency(): void
    {
        $this->givePermission('employee-competency.update');

        $employeeCompetency = $this->createEmployeeCompetency(
            attributes: [
                'score' => 70.00,
                'notes' => 'Original notes',
            ],
        );

        $this->actingAs($this->user);

        $response = $this->patchJson(
            "/api/v1/employee-competencies/{$employeeCompetency->id}",
            [
                'notes' => 'Updated notes',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.notes', 'Updated notes')
            ->assertJsonPath('data.score', '70.00');
    }

    public function test_update_rejects_duplicate_employee_competency(): void
    {
        $this->givePermission('employee-competency.update');

        $employee = $this->createEmployee();
        $competency1 = $this->createCompetency(
            code: 'COMP001',
        );
        $competency2 = $this->createCompetency(
            code: 'COMP002',
        );
        $level = $this->createCompetencyLevel();

        $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competency1,
            competencyLevel: $level,
        );

        $employeeCompetency2 = $this->createEmployeeCompetency(
            employee: $employee,
            competency: $competency2,
            competencyLevel: $level,
        );

        $this->actingAs($this->user);

        $response = $this->putJson(
            "/api/v1/employee-competencies/{$employeeCompetency2->id}",
            [
                'competency_id' => $competency1->id,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['competency_id']);
    }

    public function test_update_allows_same_competency_on_same_record(): void
    {
        $this->givePermission('employee-competency.update');

        $employeeCompetency = $this->createEmployeeCompetency();

        $this->actingAs($this->user);

        $response = $this->putJson(
            "/api/v1/employee-competencies/{$employeeCompetency->id}",
            [
                'competency_id' => $employeeCompetency->competency_id,
            ],
        );

        $response->assertOk();
    }

    public function test_returns_404_when_updating_unknown_employee_competency(): void
    {
        $this->givePermission('employee-competency.update');

        $this->actingAs($this->user);

        $response = $this->putJson(
            '/api/v1/employee-competencies/999999',
            [
                'score' => 90,
            ],
        );

        $response->assertNotFound();
    }

    public function test_can_delete_employee_competency(): void
    {
        $this->givePermission('employee-competency.delete');

        $employeeCompetency = $this->createEmployeeCompetency();

        $this->actingAs($this->user);

        $response = $this->deleteJson(
            "/api/v1/employee-competencies/{$employeeCompetency->id}",
        );

        $response
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing(
            'employee_competencies',
            [
                'id' => $employeeCompetency->id,
            ],
        );
    }

    public function test_returns_404_when_deleting_unknown_employee_competency(): void
    {
        $this->givePermission('employee-competency.delete');

        $this->actingAs($this->user);

        $response = $this->deleteJson(
            '/api/v1/employee-competencies/999999',
        );

        $response->assertNotFound();
    }
}
