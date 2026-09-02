<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveReportIndexRequest;
use App\Http\Resources\V1\Leave\LeaveReportResource;
use App\Services\Leave\LeaveReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LeaveReportController extends Controller
{
    public function __construct(
        private readonly LeaveReportService $leaveReportService,
    ) {}

    public function index(
        LeaveReportIndexRequest $request
    ): JsonResponse {
        $leaveReports = $this->leaveReportService->paginate(
            perPage: $request->integer('per_page', 15),
            employeeId: $request->integer('employee_id') ?: null,
            leaveTypeId: $request->integer('leave_type_id') ?: null,
            year: $request->integer('year') ?: null,
            status: $request->query('status'),
        );

        return ApiResponse::success(
            data: LeaveReportResource::collection($leaveReports),
            message: 'Leave Report berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $leaveReports->currentPage(),
                    'last_page' => $leaveReports->lastPage(),
                    'per_page' => $leaveReports->perPage(),
                    'total' => $leaveReports->total(),
                    'from' => $leaveReports->firstItem(),
                    'to' => $leaveReports->lastItem(),
                ],
            ],
        );
    }
}
