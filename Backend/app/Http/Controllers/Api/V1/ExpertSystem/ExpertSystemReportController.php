<?php

namespace App\Http\Controllers\Api\V1\ExpertSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpertSystem\ExpertSystemReportRequest;
use App\Http\Resources\V1\ExpertSystem\ExpertSystemReportResource;
use App\Services\ExpertSystem\ExpertSystemReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExpertSystemReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ExpertSystemReportService $expertSystemReportService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:expert_system_report.view'),
        ];
    }

    public function __invoke(
        ExpertSystemReportRequest $request,
    ): JsonResponse {
        $report = $this->expertSystemReportService->generate(
            filters: $request->validated(),
        );

        return ApiResponse::success(
            data: new ExpertSystemReportResource($report),
            message: 'Laporan expert system berhasil diambil.',
        );
    }
}
