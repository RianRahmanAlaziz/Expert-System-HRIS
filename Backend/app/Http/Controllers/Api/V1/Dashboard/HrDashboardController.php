<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Dashboard\HrDashboardResource;
use App\Services\Dashboard\HrDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class HrDashboardController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly HrDashboardService $hrDashboardService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboard.view', only: ['index']),
        ];
    }

    public function index(): JsonResponse
    {
        $summary = $this->hrDashboardService->getSummary();

        return ApiResponse::success(
            data: new HrDashboardResource($summary),
            message: 'Dashboard HR berhasil diambil.',
        );
    }
}
