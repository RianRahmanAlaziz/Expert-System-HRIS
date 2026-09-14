<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\EmployeeReportRequest;
use App\Http\Resources\V1\Dashboard\EmployeeReportResource;
use App\Services\Dashboard\EmployeeReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmployeeReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly EmployeeReportService $employeeReportService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:employee_report.view', only: ['index']),
        ];
    }

    public function index(
        EmployeeReportRequest $request,
    ): JsonResponse {
        $employees = $this->employeeReportService->paginate(
            perPage: $request->integer('per_page', 15),
            search: trim((string) $request->query('search', '')),
            departmentId: $request->integer('department_id') ?: null,
            positionId: $request->integer('position_id') ?: null,
            employmentType: $request->query('employment_type'),
            employmentStatus: $request->query('employment_status'),
        );

        return ApiResponse::success(
            data: EmployeeReportResource::collection($employees),
            message: 'Employee Report berhasil diambil.',
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
}
