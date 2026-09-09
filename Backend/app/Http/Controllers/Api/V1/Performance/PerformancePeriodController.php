<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformancePeriodIndexRequest;
use App\Http\Requests\Performance\StorePerformancePeriodRequest;
use App\Http\Requests\Performance\UpdatePerformancePeriodRequest;
use App\Http\Resources\V1\Performance\PerformancePeriodResource;
use App\Models\PerformancePeriod;
use App\Services\Performance\PerformancePeriodService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PerformancePeriodController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PerformancePeriodService $performancePeriodService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:performance_period.view', only: ['index', 'show']),
            new Middleware('permission:performance_period.create', only: ['store']),
            new Middleware('permission:performance_period.update', only: ['update']),
            new Middleware('permission:performance_period.delete', only: ['destroy']),
        ];
    }

    public function index(PerformancePeriodIndexRequest $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $search = trim(
            (string) $request->query('search', ''),
        );

        $periods = $this->performancePeriodService->paginate(
            perPage: $perPage,
            search: $search,
            status: $request->query('status'),
        );

        return ApiResponse::success(
            data: PerformancePeriodResource::collection($periods),
            message: 'Daftar Performance period berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $periods->currentPage(),
                    'last_page' => $periods->lastPage(),
                    'per_page' => $periods->perPage(),
                    'total' => $periods->total(),
                    'from' => $periods->firstItem(),
                    'to' => $periods->lastItem(),
                ],
            ],
        );
    }

    public function store(
        StorePerformancePeriodRequest $request
    ): JsonResponse {
        $period = $this->performancePeriodService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformancePeriodResource($period),
            message: 'Performance period berhasil dibuat.',
            status: 201,
        );
    }

    public function show(
        PerformancePeriod $period,
    ): JsonResponse {
        $period = $this->performancePeriodService->getById(
            $period->id,
        );

        return ApiResponse::success(
            data: new PerformancePeriodResource($period),
            message: 'Performance period berhasil diambil.',
        );
    }

    public function update(
        UpdatePerformancePeriodRequest $request,
        PerformancePeriod $period,
    ): JsonResponse {
        $period = $this->performancePeriodService->update(
            $period,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformancePeriodResource($period),
            message: 'Performance period berhasil diperbarui.',
        );
    }

    public function destroy(
        PerformancePeriod $period,
    ): JsonResponse {
        $this->performancePeriodService->delete($period);

        return ApiResponse::success(
            data: null,
            message: 'Performance period berhasil dihapus.',
        );
    }
}
