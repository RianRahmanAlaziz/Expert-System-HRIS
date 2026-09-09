<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceIndicatorIndexRequest;
use App\Http\Requests\Performance\StorePerformanceIndicatorRequest;
use App\Http\Requests\Performance\UpdatePerformanceIndicatorRequest;
use App\Http\Resources\V1\Performance\PerformanceIndicatorResource;
use App\Models\PerformanceIndicator;
use App\Services\Performance\PerformanceIndicatorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PerformanceIndicatorController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PerformanceIndicatorService $performanceIndicatorService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:performance_indicator.view', only: ['index', 'show', 'active']),
            new Middleware('permission:performance_indicator.create', only: ['store']),
            new Middleware('permission:performance_indicator.update', only: ['update']),
            new Middleware('permission:performance_indicator.delete', only: ['destroy']),
        ];
    }

    public function index(PerformanceIndicatorIndexRequest $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $search = trim(
            (string) $request->query('search', ''),
        );

        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : null;

        $indicators = $this->performanceIndicatorService->paginate(
            perPage: $perPage,
            search: $search,
            category: $request->query('category'),
            isActive: $isActive,
        );

        return ApiResponse::success(
            data: PerformanceIndicatorResource::collection($indicators),
            message: 'Daftar Performance indicator berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $indicators->currentPage(),
                    'last_page' => $indicators->lastPage(),
                    'per_page' => $indicators->perPage(),
                    'total' => $indicators->total(),
                    'from' => $indicators->firstItem(),
                    'to' => $indicators->lastItem(),
                ],
            ],
        );
    }

    public function active(): JsonResponse
    {
        $indicators = $this->performanceIndicatorService->getActive();

        return ApiResponse::success(
            data: PerformanceIndicatorResource::collection($indicators),
            message: 'Performance indicator aktif berhasil diambil.',
        );
    }

    public function store(
        StorePerformanceIndicatorRequest $request
    ): JsonResponse {
        $indicator = $this->performanceIndicatorService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceIndicatorResource($indicator),
            message: 'Performance indicator berhasil dibuat.',
            status: 201,
        );
    }

    public function show(
        PerformanceIndicator $indicator,
    ): JsonResponse {
        $indicator = $this->performanceIndicatorService->getById(
            $indicator->id,
        );

        return ApiResponse::success(
            data: new PerformanceIndicatorResource($indicator),
            message: 'Performance indicator berhasil diambil.',
        );
    }

    public function update(
        UpdatePerformanceIndicatorRequest $request,
        PerformanceIndicator $indicator
    ): JsonResponse {
        $indicator = $this->performanceIndicatorService->update(
            $indicator,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceIndicatorResource($indicator),
            message: 'Performance indicator berhasil diperbarui.',
        );
    }

    public function destroy(
        PerformanceIndicator $indicator
    ): JsonResponse {
        $this->performanceIndicatorService->delete(
            $indicator,
        );

        return ApiResponse::success(
            data: null,
            message: 'Performance indicator berhasil dihapus.',
        );
    }
}
