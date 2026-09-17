<?php

namespace Tests\Unit\Employee;

use App\Http\Requests\Employee\EmployeeIndexRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmployeeRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | StoreEmployeeRequest
    |--------------------------------------------------------------------------
    */

    public function test_store_request_accepts_valid_data(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $user = User::factory()->create();

        $data = $this->validStoreData(
            departmentId: $department->id,
            positionId: $position->id,
            userId: $user->id,
        );

        $validator = Validator::make(
            $data,
            (new StoreEmployeeRequest())->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_requires_required_fields(): void
    {
        $validator = Validator::make(
            [],
            (new StoreEmployeeRequest())->rules(),
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();

        foreach (
            [
                'department_id',
                'position_id',
                'employee_number',
                'first_name',
                'gender',
                'join_date',
                'employment_type',
                'employment_status',
            ] as $field
        ) {
            $this->assertTrue($errors->has($field));
        }
    }

    public function test_store_request_rejects_invalid_foreign_keys(): void
    {
        $data = $this->validStoreData(
            departmentId: 999999,
            positionId: 999999,
            userId: 999999,
        );

        $validator = Validator::make(
            $data,
            (new StoreEmployeeRequest())->rules(),
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();

        $this->assertTrue($errors->has('user_id'));
        $this->assertTrue($errors->has('department_id'));
        $this->assertTrue($errors->has('position_id'));
    }

    public function test_store_request_rejects_duplicate_user_and_employee_number(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $user = User::factory()->create();

        $employee = $this->createEmployee(
            user: $user,
            departmentId: $department->id,
            positionId: $position->id,
        );

        $data = $this->validStoreData(
            departmentId: $department->id,
            positionId: $position->id,
            userId: $user->id,
        );

        $data['employee_number'] = $employee->employee_number;

        $validator = Validator::make(
            $data,
            (new StoreEmployeeRequest())->rules(),
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();

        $this->assertTrue($errors->has('user_id'));
        $this->assertTrue($errors->has('employee_number'));
    }

    public function test_store_request_allows_nullable_fields(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $data = $this->validStoreData(
            departmentId: $department->id,
            positionId: $position->id,
        );

        $data['user_id'] = null;
        $data['manager_id'] = null;
        $data['last_name'] = null;
        $data['birth_date'] = null;
        $data['phone'] = null;
        $data['address'] = null;
        $data['history_reason'] = null;
        $data['history_notes'] = null;

        $validator = Validator::make(
            $data,
            (new StoreEmployeeRequest())->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_rejects_invalid_dates(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();

        $data = $this->validStoreData(
            departmentId: $department->id,
            positionId: $position->id,
        );

        $data['birth_date'] = 'not-a-date';
        $data['join_date'] = 'not-a-date';

        $validator = Validator::make(
            $data,
            (new StoreEmployeeRequest())->rules(),
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();

        $this->assertTrue($errors->has('birth_date'));
        $this->assertTrue($errors->has('join_date'));
    }

    /*
    |--------------------------------------------------------------------------
    | UpdateEmployeeRequest
    |--------------------------------------------------------------------------
    */

    public function test_update_request_accepts_valid_partial_data(): void
    {
        $employee = $this->createEmployee();

        $request = $this->makeUpdateRequest($employee);

        $data = [
            'first_name' => 'Updated Name',
            'phone' => '08123456789',
            'employment_status' => 'active',
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_allows_empty_payload(): void
    {
        $employee = $this->createEmployee();

        $request = $this->makeUpdateRequest($employee);

        $validator = Validator::make(
            [],
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_rejects_duplicate_user_and_employee_number(): void
    {
        $employee = $this->createEmployee();
        $otherUser = User::factory()->create();

        $otherEmployee = $this->createEmployee(
            user: $otherUser,
        );

        $request = $this->makeUpdateRequest($employee);

        $data = [
            'user_id' => $otherUser->id,
            'employee_number' => $otherEmployee->employee_number,
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();

        $this->assertTrue($errors->has('user_id'));
        $this->assertTrue($errors->has('employee_number'));
    }

    public function test_update_request_allows_current_user_and_employee_number(): void
    {
        $user = User::factory()->create();

        $employee = $this->createEmployee(
            user: $user,
        );

        $request = $this->makeUpdateRequest($employee);

        $data = [
            'user_id' => $user->id,
            'employee_number' => $employee->employee_number,
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_update_request_rejects_employee_as_own_manager(): void
    {
        $employee = $this->createEmployee();

        $request = $this->makeUpdateRequest($employee);

        $data = [
            'manager_id' => $employee->id,
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertTrue(
            $validator->errors()->has('manager_id')
        );
    }

    public function test_update_request_rejects_invalid_foreign_keys(): void
    {
        $employee = $this->createEmployee();

        $request = $this->makeUpdateRequest($employee);

        $data = [
            'user_id' => 999999,
            'department_id' => 999999,
            'position_id' => 999999,
            'manager_id' => 999999,
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();

        $this->assertTrue($errors->has('user_id'));
        $this->assertTrue($errors->has('department_id'));
        $this->assertTrue($errors->has('position_id'));
        $this->assertTrue($errors->has('manager_id'));
    }

    public function test_update_request_rejects_future_effective_date(): void
    {
        $employee = $this->createEmployee();

        $request = $this->makeUpdateRequest($employee);

        $data = [
            'effective_date' => now()->addDay()->toDateString(),
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertTrue($validator->fails());

        $this->assertTrue(
            $validator->errors()->has('effective_date')
        );
    }

    public function test_update_request_accepts_today_as_effective_date(): void
    {
        $employee = $this->createEmployee();

        $request = $this->makeUpdateRequest($employee);

        $data = [
            'effective_date' => now()->toDateString(),
        ];

        $validator = Validator::make(
            $data,
            $request->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    /*
    |--------------------------------------------------------------------------
    | EmployeeIndexRequest
    |--------------------------------------------------------------------------
    */

    public function test_index_request_accepts_valid_filters(): void
    {
        $department = $this->createDepartment();
        $position = $this->createPosition();
        $manager = $this->createEmployee();

        $data = [
            'search' => 'John',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $manager->id,
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'per_page' => 50,
        ];

        $validator = Validator::make(
            $data,
            (new EmployeeIndexRequest())->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_index_request_allows_empty_filters(): void
    {
        $validator = Validator::make(
            [],
            (new EmployeeIndexRequest())->rules(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_index_request_rejects_invalid_foreign_keys(): void
    {
        $data = [
            'department_id' => 999999,
            'position_id' => 999999,
            'manager_id' => 999999,
        ];

        $validator = Validator::make(
            $data,
            (new EmployeeIndexRequest())->rules(),
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();

        $this->assertTrue($errors->has('department_id'));
        $this->assertTrue($errors->has('position_id'));
        $this->assertTrue($errors->has('manager_id'));
    }

    public function test_index_request_rejects_invalid_per_page(): void
    {
        $rules = (new EmployeeIndexRequest())->rules();

        foreach ([0, 101, 'invalid'] as $perPage) {
            $validator = Validator::make(
                ['per_page' => $perPage],
                $rules,
            );

            $this->assertTrue(
                $validator->fails(),
                "per_page value [{$perPage}] should be rejected."
            );

            $this->assertTrue(
                $validator->errors()->has('per_page')
            );
        }
    }

    public function test_index_request_rejects_search_and_string_fields_over_max_length(): void
    {
        $rules = (new EmployeeIndexRequest())->rules();

        $data = [
            'search' => str_repeat('a', 101),
            'employment_type' => str_repeat('a', 51),
            'employment_status' => str_repeat('a', 51),
        ];

        $validator = Validator::make(
            $data,
            $rules,
        );

        $this->assertTrue($validator->fails());

        $errors = $validator->errors();

        $this->assertTrue($errors->has('search'));
        $this->assertTrue($errors->has('employment_type'));
        $this->assertTrue($errors->has('employment_status'));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function makeUpdateRequest(Employee $employee): UpdateEmployeeRequest
    {
        $request = UpdateEmployeeRequest::create(
            uri: "/api/v1/employees/{$employee->id}",
            method: 'PUT',
        );

        $route = new Route(
            methods: ['PUT'],
            uri: 'api/v1/employees/{employee}',
            action: [],
        );

        $route->bind($request);

        $route->setParameter(
            'employee',
            $employee,
        );

        $request->setRouteResolver(
            fn() => $route
        );

        return $request;
    }

    private function validStoreData(
        int $departmentId,
        int $positionId,
        ?int $userId = null,
    ): array {
        return [
            'user_id' => $userId,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'manager_id' => null,
            'employee_number' => 'EMP-' . fake()->unique()->numerify('#####'),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '1995-01-01',
            'phone' => '08123456789',
            'address' => 'Test Address',
            'join_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'history_reason' => 'Initial employment',
            'history_notes' => 'Test employee',
        ];
    }

    private function createDepartment(): Department
    {
        return Department::query()->create([
            'code' => 'DEP-' . fake()->unique()->numerify('####'),
            'name' => 'Department ' . fake()->unique()->numerify('####'),
            'description' => 'Test department',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createPosition(): Position
    {
        return Position::query()->create([
            'code' => 'POS-' . fake()->unique()->numerify('####'),
            'name' => 'Position ' . fake()->unique()->numerify('####'),
            'description' => 'Test position',
            'level' => 1,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    private function createEmployee(
        ?User $user = null,
        ?int $departmentId = null,
        ?int $positionId = null,
        ?int $managerId = null,
    ): Employee {
        $department = $departmentId !== null
            ? Department::query()->findOrFail($departmentId)
            : $this->createDepartment();

        $position = $positionId !== null
            ? Position::query()->findOrFail($positionId)
            : $this->createPosition();

        return Employee::query()->create([
            'user_id' => $user?->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'manager_id' => $managerId,
            'employee_number' => 'EMP-' . fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => 'male',
            'birth_date' => '1995-01-01',
            'phone' => '08123456789',
            'address' => 'Test Address',
            'join_date' => '2025-01-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);
    }
}
