<?php

namespace App\Http\Controllers\Api\V1\Competency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competency\CompetencyReportRequest;
use App\Http\Resources\V1\Competency\CompetencyReportResource;
use App\Services\Competency\CompetencyReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CompetencyReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CompetencyReportService $competencyReportService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:competency_report.view',   only: ['index']),
        ];
    }

    public function index(
        CompetencyReportRequest $request,
    ): JsonResponse {
        $report = $this->competencyReportService->generate(
            filters: [
                'employee_id' => $request->integer('employee_id') ?: null,
                'competency_id' => $request->integer('competency_id') ?: null,
                'department_id' => $request->integer('department_id') ?: null,
                'position_id' => $request->integer('position_id') ?: null,
            ],
        );

        return ApiResponse::success(
            data: CompetencyReportResource::collection($report),
            message: 'Competency Report berhasil diambil.',
        );
    }
}
