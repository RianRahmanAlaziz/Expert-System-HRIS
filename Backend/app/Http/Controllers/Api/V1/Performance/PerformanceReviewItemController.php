<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\StorePerformanceReviewItemRequest;
use App\Http\Requests\Performance\UpdatePerformanceReviewItemRequest;
use App\Http\Resources\V1\Performance\PerformanceReviewItemResource;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Services\Performance\PerformanceReviewItemService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PerformanceReviewItemController extends Controller
{
    public function __construct(
        private readonly PerformanceReviewItemService $performanceReviewItemService,
    ) {}

    public function index(
        PerformanceReview $performanceReview
    ): JsonResponse {
        $items = $this->performanceReviewItemService->getByReview(
            $performanceReview,
        );

        return ApiResponse::success(
            data: PerformanceReviewItemResource::collection($items),
            message: 'Performance review item berhasil diambil.',
        );
    }

    public function store(
        StorePerformanceReviewItemRequest $request,
        PerformanceReview $performanceReview
    ): JsonResponse {
        $item = $this->performanceReviewItemService->create(
            $performanceReview,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceReviewItemResource($item),
            message: 'Performance review item berhasil dibuat.',
            status: 201,
        );
    }

    public function show(
        PerformanceReviewItem $performanceReviewItem
    ): JsonResponse {
        $item = $this->performanceReviewItemService->getById(
            $performanceReviewItem,
        );

        return ApiResponse::success(
            data: new PerformanceReviewItemResource($item),
            message: 'Performance review item berhasil diambil.',
        );
    }

    public function update(
        UpdatePerformanceReviewItemRequest $request,
        PerformanceReviewItem $performanceReviewItem
    ): JsonResponse {
        $item = $this->performanceReviewItemService->update(
            $performanceReviewItem,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceReviewItemResource($item),
            message: 'Performance review item berhasil diperbarui.',
        );
    }

    public function destroy(
        PerformanceReviewItem $performanceReviewItem
    ): JsonResponse {
        $this->performanceReviewItemService->delete(
            $performanceReviewItem,
        );

        return ApiResponse::success(
            message: 'Performance review item berhasil dihapus.',
            status: 204,
        );
    }
}
