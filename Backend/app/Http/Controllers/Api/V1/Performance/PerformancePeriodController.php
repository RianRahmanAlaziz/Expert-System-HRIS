<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\StorePerformancePeriodRequest;
use App\Http\Requests\Performance\UpdatePerformancePeriodRequest;
use App\Http\Resources\V1\Performance\PerformancePeriodResource;
use App\Models\PerformancePeriod;
use App\Services\Performance\PerformancePeriodService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PerformancePeriodController extends Controller
{
    public function __construct(
        private readonly PerformancePeriodService $performancePeriodService,
    ) {}

    public function index(): JsonResponse
    {
        $periods = $this->performancePeriodService->getAll();

        return ApiResponse::success(
            data: PerformancePeriodResource::collection($periods),
            message: 'Performance period berhasil diambil.',
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
        PerformancePeriod $performancePeriod
    ): JsonResponse {
        $period = $this->performancePeriodService->getById(
            $performancePeriod->id,
        );

        return ApiResponse::success(
            data: new PerformancePeriodResource($period),
            message: 'Performance period berhasil diambil.',
        );
    }

    public function update(
        UpdatePerformancePeriodRequest $request,
        PerformancePeriod $performancePeriod
    ): JsonResponse {
        $period = $this->performancePeriodService->update(
            $performancePeriod,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformancePeriodResource($period),
            message: 'Performance period berhasil diperbarui.',
        );
    }

    public function destroy(
        PerformancePeriod $performancePeriod
    ): JsonResponse {
        $this->performancePeriodService->delete(
            $performancePeriod,
        );

        return ApiResponse::success(
            message: 'Performance period berhasil dihapus.',
            status: 204,
        );
    }
}
