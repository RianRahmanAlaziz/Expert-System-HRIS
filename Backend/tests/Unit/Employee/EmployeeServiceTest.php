<?php

namespace Tests\Unit\Employee;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Services\Employee\EmployeeService;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use RuntimeException;

class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeService $employeeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employeeService = app(EmployeeService::class);
    }

    private function createPrivilegedUser(): User
    {
        $user = User::factory()->create();

        $permission = Permission::findOrCreate(
            'employee.view',
            'web',
        );

        $role = Role::findOrCreate(
            'hr-admin',
            'web',
        );

        $role->givePermissionTo($permission);
        $user->assignRole($role);

        return $user;
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'HR',
            'name' => 'Human Resources',
            'description' => 'HR Department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => 'HR-STAFF',
            'name' => 'HR Staff',
            'description' => 'HR Staff Position',
            'level' => 3,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function employeeData(
        Department $department,
        Position $position,
        ?int $userId = null,
        ?int $managerId = null,
        string $employeeNumber = 'EMP-001',
    ): array {
        return [
            'user_id' => $userId,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $managerId,
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
            'history_reason' => 'Initial employment',
            'history_notes' => 'Employee joined the company.',
        ];
    }

    public function test_it_can_create_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $user = User::factory()->create();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                userId: $user->id,
            ),
        );

        $this->assertInstanceOf(Employee::class, $employee);

        $this->assertSame('EMP-001', $employee->employee_number);
        $this->assertSame('John', $employee->first_name);
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame($user->id, $employee->user_id);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'employee_number' => 'EMP-001',
            'first_name' => 'John',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('employment_histories', [
            'employee_id' => $employee->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'employment_type' => 'full_time',
            'start_date' => '2026-01-01 00:00:00',
        ]);
    }

    public function test_it_can_create_employee_without_user(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $this->assertNull($employee->user_id);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'user_id' => null,
        ]);
    }

    public function test_it_can_find_employee_by_id(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $user = $this->createPrivilegedUser();

        $result = $this->employeeService->findById(
            user: $user,
            id: $employee->id,
        );

        $this->assertInstanceOf(Employee::class, $result);
        $this->assertSame($employee->id, $result->id);

        $this->assertTrue($result->relationLoaded('department'));
        $this->assertTrue($result->relationLoaded('position'));
        $this->assertTrue($result->relationLoaded('employmentHistories'));
    }

    public function test_it_throws_exception_when_employee_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $user = $this->createPrivilegedUser();

        $this->employeeService->findById(
            user: $user,
            id: 999999,
        );
    }

    public function test_it_can_search_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-001',
            ),
        );

        $this->employeeService->create(
            array_merge(
                $this->employeeData(
                    department: $department,
                    position: $position,
                    employeeNumber: 'EMP-002',
                ),
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                ],
            ),
        );

        $user = $this->createPrivilegedUser();

        $result = $this->employeeService->paginate(
            user: $user,
            perPage: 15,
            search: 'Jane',
        );

        $this->assertSame(1, $result->total());
        $this->assertSame(
            'Jane',
            $result->items()[0]->first_name
        );
    }

    public function test_it_can_filter_employee_by_department(): void
    {
        $hrDepartment = $this->createDepartment();

        $itDepartment = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'description' => 'IT Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = $this->createPosition();

        $this->employeeService->create(
            $this->employeeData(
                department: $hrDepartment,
                position: $position,
                employeeNumber: 'EMP-001',
            ),
        );

        $this->employeeService->create(
            $this->employeeData(
                department: $itDepartment,
                position: $position,
                employeeNumber: 'EMP-002',
            ),
        );

        $user = $this->createPrivilegedUser();

        $result = $this->employeeService->paginate(
            user: $user,
            perPage: 15,
            departmentId: $itDepartment->id,
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'EMP-002',
            $result->items()[0]->employee_number
        );
    }

    public function test_it_can_filter_employee_by_position(): void
    {
        $department = $this->createDepartment();

        $staffPosition = $this->createPosition();

        $managerPosition = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'HR Manager Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $staffPosition,
                employeeNumber: 'EMP-001',
            ),
        );

        $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $managerPosition,
                employeeNumber: 'EMP-002',
            ),
        );

        $user = $this->createPrivilegedUser();

        $result = $this->employeeService->paginate(
            user: $user,
            perPage: 15,
            positionId: $managerPosition->id,
        );

        $this->assertSame(1, $result->total());

        $this->assertSame(
            'EMP-002',
            $result->items()[0]->employee_number
        );
    }

    public function test_it_can_update_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $result = $this->employeeService->update(
            $employee,
            [
                'first_name' => 'Jonathan',
                'last_name' => 'Updated',
                'employment_status' => 'inactive',
            ],
        );

        $this->assertSame(
            'Jonathan',
            $result->first_name
        );

        $this->assertSame(
            'Updated',
            $result->last_name
        );

        $this->assertSame(
            'inactive',
            $result->employment_status
        );

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Jonathan',
            'last_name' => 'Updated',
            'employment_status' => 'inactive',
        ]);
    }

    public function test_it_can_delete_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $this->employeeService->delete($employee);

        $this->assertSoftDeleted('employees', [
            'id' => $employee->id,
        ]);
    }

    public function test_it_logs_activity_when_creating_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $activityLog = ActivityLog::query()
            ->where('action', 'create')
            ->where('module', 'employee')
            ->where('target_id', $employee->id)
            ->firstOrFail();

        $this->assertSame(
            'EMP-001',
            $activityLog->new_values['employee_number']
        );

        $this->assertSame(
            'John',
            $activityLog->new_values['first_name']
        );

        $this->assertSame(
            $department->id,
            $activityLog->new_values['department_id']
        );

        $this->assertSame(
            $position->id,
            $activityLog->new_values['position_id']
        );

        $this->assertSame(
            'active',
            $activityLog->new_values['employment_status']
        );
    }

    public function test_it_logs_activity_when_updating_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $this->employeeService->update(
            $employee,
            [
                'first_name' => 'Jonathan',
                'last_name' => 'Updated',
                'employment_status' => 'inactive',
            ],
        );

        $activityLog = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'employee')
            ->where('target_id', $employee->id)
            ->firstOrFail();

        $this->assertSame(
            'John',
            $activityLog->old_values['first_name']
        );

        $this->assertSame(
            'Jonathan',
            $activityLog->new_values['first_name']
        );

        $this->assertSame(
            'Doe',
            $activityLog->old_values['last_name']
        );

        $this->assertSame(
            'Updated',
            $activityLog->new_values['last_name']
        );

        $this->assertSame(
            'active',
            $activityLog->old_values['employment_status']
        );

        $this->assertSame(
            'inactive',
            $activityLog->new_values['employment_status']
        );
    }

    public function test_it_logs_activity_when_deleting_employee(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $this->employeeService->delete($employee);

        $activityLog = ActivityLog::query()
            ->where('action', 'delete')
            ->where('module', 'employee')
            ->where('target_id', $employee->id)
            ->firstOrFail();

        $this->assertSame(
            'EMP-001',
            $activityLog->old_values['employee_number']
        );

        $this->assertSame(
            'John',
            $activityLog->old_values['first_name']
        );

        $this->assertSame(
            $department->id,
            $activityLog->old_values['department_id']
        );

        $this->assertSame(
            $position->id,
            $activityLog->old_values['position_id']
        );
    }

    public function test_it_rejects_inactive_department_when_creating_employee(): void
    {
        $department = $this->createDepartment();

        $department->update([
            'is_active' => false,
        ]);

        $position = $this->createPosition();

        $this->expectException(ValidationException::class);

        $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );
    }

    public function test_it_rejects_inactive_position_when_creating_employee(): void
    {
        $department = $this->createDepartment();

        $position = $this->createPosition();

        $position->update([
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );
    }

    public function test_it_rejects_inactive_department_when_updating_employee(): void
    {
        $oldDepartment = $this->createDepartment();

        $newDepartment = Department::query()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'description' => 'IT Department',
            'status' => 'active',
            'is_active' => true,
        ]);

        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $oldDepartment,
                position: $position,
            ),
        );

        $newDepartment->update([
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        $this->employeeService->update(
            $employee,
            [
                'department_id' => $newDepartment->id,
                'effective_date' => now()->toDateString(),
            ],
        );
    }

    public function test_it_rejects_inactive_position_when_updating_employee(): void
    {
        $department = $this->createDepartment();

        $oldPosition = $this->createPosition();

        $newPosition = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'HR Manager Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $oldPosition,
            ),
        );

        $newPosition->update([
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        $this->employeeService->update(
            $employee,
            [
                'position_id' => $newPosition->id,
                'effective_date' => now()->toDateString(),
            ],
        );
    }

    public function test_it_rejects_employee_as_own_manager(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
            ),
        );

        $this->expectException(ValidationException::class);

        $this->employeeService->update(
            $employee,
            [
                'manager_id' => $employee->id,
                'effective_date' => now()->toDateString(),
            ],
        );
    }

    public function test_it_rejects_circular_manager_hierarchy(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeA = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-001',
            ),
        );

        $employeeB = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-002',
                managerId: $employeeA->id,
            ),
        );

        $this->expectException(ValidationException::class);

        $this->employeeService->update(
            $employeeA,
            [
                'manager_id' => $employeeB->id,
                'effective_date' => now()->toDateString(),
            ],
        );
    }

    public function test_it_rejects_deep_circular_manager_hierarchy(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employeeA = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-001',
            ),
        );

        $employeeB = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-002',
                managerId: $employeeA->id,
            ),
        );

        $employeeC = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-003',
                managerId: $employeeB->id,
            ),
        );

        $this->expectException(ValidationException::class);

        $this->employeeService->update(
            $employeeA,
            [
                'manager_id' => $employeeC->id,
                'effective_date' => now()->toDateString(),
            ],
        );
    }

    public function test_it_accepts_valid_manager_hierarchy(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $manager = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-001',
            ),
        );

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-002',
            ),
        );

        $result = $this->employeeService->update(
            $employee,
            [
                'manager_id' => $manager->id,
                'effective_date' => now()->toDateString(),
            ],
        );

        $this->assertSame(
            $manager->id,
            $result->manager_id,
        );

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'manager_id' => $manager->id,
        ]);
    }

    public function test_it_requires_effective_date_when_employment_data_changes(): void
    {
        $department = $this->createDepartment();
        $oldPosition = $this->createPosition();

        $newPosition = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'HR Manager Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $employee = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $oldPosition,
            ),
        );

        $this->expectException(ValidationException::class);

        $this->employeeService->update(
            $employee,
            [
                'position_id' => $newPosition->id,
            ],
        );
    }

    public function test_it_rejects_effective_date_before_current_history_start(): void
    {
        $department = $this->createDepartment();
        $oldPosition = $this->createPosition();

        $newPosition = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'HR Manager Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $employee = $this->employeeService->create(
            array_merge(
                $this->employeeData(
                    department: $department,
                    position: $oldPosition,
                ),
                [
                    'join_date' => '2026-01-01',
                ],
            ),
        );

        $this->expectException(ValidationException::class);

        $this->employeeService->update(
            $employee,
            [
                'position_id' => $newPosition->id,
                'effective_date' => '2025-12-31',
            ],
        );
    }

    public function test_it_creates_new_history_when_position_changes(): void
    {
        $department = $this->createDepartment();

        $oldPosition = $this->createPosition();

        $newPosition = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'HR Manager Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $employee = $this->employeeService->create(
            array_merge(
                $this->employeeData(
                    department: $department,
                    position: $oldPosition,
                ),
                [
                    'join_date' => '2026-01-01',
                ],
            ),
        );

        $effectiveDate = now()->toDateString();

        $result = $this->employeeService->update(
            $employee,
            [
                'position_id' => $newPosition->id,
                'effective_date' => $effectiveDate,
                'history_reason' => 'Promotion',
                'history_notes' => 'Promotion to new position.',
            ],
        );

        $this->assertSame(
            $newPosition->id,
            $result->position_id,
        );

        $oldHistory = $employee
            ->employmentHistories()
            ->where('position_id', $oldPosition->id)
            ->first();

        $this->assertNotNull($oldHistory);

        $this->assertSame(
            $effectiveDate,
            $oldHistory->end_date->addDay()->toDateString(),
        );

        $newHistory = $employee
            ->employmentHistories()
            ->where('position_id', $newPosition->id)
            ->whereNull('end_date')
            ->first();

        $this->assertNotNull($newHistory);

        $this->assertSame(
            $effectiveDate,
            $newHistory->start_date->toDateString(),
        );

        $this->assertSame(
            'Promotion',
            $newHistory->reason,
        );

        $this->assertSame(
            'Promotion to new position.',
            $newHistory->notes,
        );
    }

    public function test_it_creates_new_history_when_employment_type_changes(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $employee = $this->employeeService->create(
            array_merge(
                $this->employeeData(
                    department: $department,
                    position: $position,
                ),
                [
                    'join_date' => '2026-01-01',
                    'employment_type' => 'full_time',
                ],
            ),
        );

        $effectiveDate = now()->toDateString();

        $result = $this->employeeService->update(
            $employee,
            [
                'employment_type' => 'contract',
                'effective_date' => $effectiveDate,
                'history_reason' => 'Employment type change',
                'history_notes' => 'Changed from full time to contract.',
            ],
        );

        $this->assertSame(
            'contract',
            $result->employment_type,
        );

        $oldHistory = $employee
            ->employmentHistories()
            ->where('employment_type', 'full_time')
            ->first();

        $this->assertNotNull($oldHistory);

        $this->assertSame(
            $effectiveDate,
            $oldHistory->end_date->addDay()->toDateString(),
        );

        $newHistory = $employee
            ->employmentHistories()
            ->where('employment_type', 'contract')
            ->whereNull('end_date')
            ->first();

        $this->assertNotNull($newHistory);

        $this->assertSame(
            $effectiveDate,
            $newHistory->start_date->toDateString(),
        );

        $this->assertSame(
            'Employment type change',
            $newHistory->reason,
        );

        $this->assertSame(
            'Changed from full time to contract.',
            $newHistory->notes,
        );
    }

    public function test_it_creates_new_history_when_manager_changes(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $oldManager = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-MGR-001',
            ),
        );

        $newManager = $this->employeeService->create(
            $this->employeeData(
                department: $department,
                position: $position,
                employeeNumber: 'EMP-MGR-002',
            ),
        );

        $employee = $this->employeeService->create(
            array_merge(
                $this->employeeData(
                    department: $department,
                    position: $position,
                    managerId: $oldManager->id,
                    employeeNumber: 'EMP-003',
                ),
                [
                    'join_date' => '2026-01-01',
                ],
            ),
        );

        $effectiveDate = now()->toDateString();

        $result = $this->employeeService->update(
            $employee,
            [
                'manager_id' => $newManager->id,
                'effective_date' => $effectiveDate,
                'history_reason' => 'Manager change',
                'history_notes' => 'Employee assigned to a new manager.',
            ],
        );

        $this->assertSame(
            $newManager->id,
            $result->manager_id,
        );

        $oldHistory = $employee
            ->employmentHistories()
            ->where('manager_id', $oldManager->id)
            ->first();

        $this->assertNotNull($oldHistory);

        $this->assertSame(
            $effectiveDate,
            $oldHistory->end_date->addDay()->toDateString(),
        );

        $newHistory = $employee
            ->employmentHistories()
            ->where('manager_id', $newManager->id)
            ->whereNull('end_date')
            ->first();

        $this->assertNotNull($newHistory);

        $this->assertSame(
            $effectiveDate,
            $newHistory->start_date->toDateString(),
        );

        $this->assertSame(
            'Manager change',
            $newHistory->reason,
        );

        $this->assertSame(
            'Employee assigned to a new manager.',
            $newHistory->notes,
        );
    }

    public function test_it_logs_employment_change_activity(): void
    {
        $department = $this->createDepartment();

        $oldPosition = $this->createPosition();

        $newPosition = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'HR Manager Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $employee = $this->employeeService->create(
            array_merge(
                $this->employeeData(
                    department: $department,
                    position: $oldPosition,
                ),
                [
                    'join_date' => '2026-01-01',
                ],
            ),
        );

        $effectiveDate = now()->toDateString();

        $this->employeeService->update(
            $employee,
            [
                'position_id' => $newPosition->id,
                'effective_date' => $effectiveDate,
                'history_reason' => 'Promotion',
                'history_notes' => 'Promotion to new position.',
            ],
        );

        $activityLog = ActivityLog::query()
            ->where('action', 'update')
            ->where('module', 'employee')
            ->where('target_id', $employee->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $oldPosition->id,
            $activityLog->old_values['position_id'],
        );

        $this->assertSame(
            $newPosition->id,
            $activityLog->new_values['position_id'],
        );

        $this->assertSame(
            $effectiveDate,
            $activityLog->new_values['effective_date'],
        );

        $this->assertSame(
            'Promotion',
            $activityLog->new_values['history_reason'],
        );

        $this->assertSame(
            'Promotion to new position.',
            $activityLog->new_values['history_notes'],
        );
    }

    public function test_it_rolls_back_employee_creation_when_activity_log_fails(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock
                ->shouldReceive('log')
                ->once()
                ->andThrow(new RuntimeException('Activity log failed.'));
        });

        $employeeService = app(EmployeeService::class);

        $this->expectException(RuntimeException::class);

        try {
            $employeeService->create(
                $this->employeeData(
                    department: $department,
                    position: $position,
                ),
            );
        } finally {
            $this->assertDatabaseMissing('employees', [
                'employee_number' => 'EMP-001',
            ]);

            $this->assertDatabaseCount(
                'employment_histories',
                0,
            );

            $this->assertDatabaseCount(
                'activity_logs',
                0,
            );
        }
    }

    public function test_it_rolls_back_employee_update_when_activity_log_fails(): void
    {
        $department = $this->createDepartment();

        $oldPosition = $this->createPosition();

        $newPosition = Position::query()->create([
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'description' => 'HR Manager Position',
            'level' => 5,
            'status' => 'active',
            'is_active' => true,
        ]);

        $employee = $this->employeeService->create(
            array_merge(
                $this->employeeData(
                    department: $department,
                    position: $oldPosition,
                ),
                [
                    'join_date' => '2026-01-01',
                ],
            ),
        );

        $originalHistoryCount = $employee
            ->employmentHistories()
            ->count();

        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock
                ->shouldReceive('log')
                ->once()
                ->andThrow(new RuntimeException('Activity log failed.'));
        });

        $employeeService = app(EmployeeService::class);

        $this->expectException(RuntimeException::class);

        try {
            $employeeService->update(
                $employee,
                [
                    'position_id' => $newPosition->id,
                    'effective_date' => now()->toDateString(),
                    'history_reason' => 'Promotion',
                    'history_notes' => 'Promotion to new position.',
                ],
            );
        } finally {
            $employee->refresh();

            $this->assertSame(
                $oldPosition->id,
                $employee->position_id,
            );

            $this->assertSame(
                $originalHistoryCount,
                $employee->employmentHistories()->count(),
            );

            $currentHistory = $employee
                ->employmentHistories()
                ->whereNull('end_date')
                ->first();

            $this->assertNotNull($currentHistory);

            $this->assertSame(
                $oldPosition->id,
                $currentHistory->position_id,
            );

            $this->assertSame(
                '2026-01-01',
                $currentHistory->start_date->toDateString(),
            );

            $this->assertNull(
                $currentHistory->end_date,
            );

            $this->assertDatabaseMissing('employment_histories', [
                'employee_id' => $employee->id,
                'position_id' => $newPosition->id,
            ]);

            $this->assertDatabaseCount(
                'activity_logs',
                1,
            );
        }
    }
}
