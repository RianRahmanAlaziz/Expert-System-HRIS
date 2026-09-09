<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Performance\PerformanceHistoryResource;
use App\Models\Employee;
use App\Services\Performance\PerformanceHistoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PerformanceHistoryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PerformanceHistoryService $performanceHistoryService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:performance_review.view', only: ['index', 'employee']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $history = $this->performanceHistoryService->paginateHistory(
            user: $request->user(),
            perPage: $perPage,
        );

        return ApiResponse::success(
            data: PerformanceHistoryResource::collection($history),
            message: 'Performance history berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'from' => $history->firstItem(),
                    'to' => $history->lastItem(),
                ],
            ],
        );
    }

    public function employee(
        Request $request,
        Employee $employee
    ): JsonResponse {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $history = $this->performanceHistoryService->paginateHistory(
            user: $request->user(),
            perPage: $perPage,
            employee: $employee,
        );

        return ApiResponse::success(
            data: PerformanceHistoryResource::collection($history),
            message: 'Performance history employee berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'last_page' => $history->lastPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'from' => $history->firstItem(),
                    'to' => $history->lastItem(),
                ],
            ],
        );
    }
}
