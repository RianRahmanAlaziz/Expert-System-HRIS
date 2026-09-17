<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\EmployeeIndexRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\V1\Employee\EmployeeResource;
use App\Models\Employee;
use App\Services\Employee\EmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:employee.view', only: ['index', 'show']),
            new Middleware('permission:employee.create', only: ['store']),
            new Middleware('permission:employee.update', only: ['update']),
            new Middleware('permission:employee.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(EmployeeIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $employees = $this->employeeService->paginate(
            user: $request->user(),
            perPage: $request->integer('per_page', 15),
            search: $request->query('search'),
            departmentId: $request->integer('department_id') ?: null,
            positionId: $request->integer('position_id') ?: null,
            managerId: $request->integer('manager_id') ?: null,
            employmentType: $request->query('employment_type'),
            employmentStatus: $request->query('employment_status'),
        );

        return ApiResponse::success(
            data: EmployeeResource::collection($employees),
            message: 'Daftar Employee berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total(),
                    'from' => $employees->firstItem(),
                    'to' => $employees->lastItem(),
                ],
            ],
        );
    }

    public function show(
        Request $request,
        Employee $employee
    ): JsonResponse {
        Gate::authorize('view', $employee);

        $employee = $this->employeeService->findById(
            user: $request->user(),
            id: $employee->id,
        );

        return ApiResponse::success(
            data: EmployeeResource::make($employee),
            message: 'Detail Employee berhasil diambil.',
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        $employee = $this->employeeService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: EmployeeResource::make($employee),
            message: 'Employee berhasil dibuat.',
            status: 201,
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee
    ): JsonResponse {
        Gate::authorize('update', $employee);
        $employee = $this->employeeService->update(
            $employee,
            $request->validated(),
        );

        return ApiResponse::success(
            data: EmployeeResource::make($employee),
            message: 'Employee berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('delete', $employee);
        $this->employeeService->delete($employee);

        return ApiResponse::success(
            data: null,
            message: 'Employee berhasil dihapus.'
        );
    }
}
