<?php

namespace App\Http\Controllers\Api\V1\Recommendation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recommendation\RecommendationReportRequest;
use App\Http\Resources\V1\Recommendation\RecommendationReportResource;
use App\Services\Recommendation\RecommendationReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RecommendationReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly RecommendationReportService $recommendationReportService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:recommendation_report.view'),
        ];
    }

    public function __invoke(
        RecommendationReportRequest $request,
    ): JsonResponse {
        $report = $this->recommendationReportService->generate(
            filters: $request->validated(),
        );

        return ApiResponse::success(
            data: new RecommendationReportResource($report),
            message: 'Laporan recommendation berhasil diambil.',
        );
    }
}
