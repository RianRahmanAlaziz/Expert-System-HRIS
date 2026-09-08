<?php

namespace App\Http\Controllers\Api\V1\Promotion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\StorePromotionAssessmentItemRequest;
use App\Http\Requests\Promotion\UpdatePromotionAssessmentItemRequest;
use App\Http\Resources\V1\Promotion\PromotionAssessmentItemResource;
use App\Models\PromotionAssessmentItem;
use App\Services\Promotion\PromotionAssessmentItemService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PromotionAssessmentItemController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PromotionAssessmentItemService $promotionAssessmentItemService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:promotion_assessment_item.view',  only: ['index', 'show', 'byAssessment']),
            new Middleware('permission:promotion_assessment_item.create', only: ['store']),
            new Middleware('permission:promotion_assessment_item.update',  only: ['update']),
            new Middleware('permission:promotion_assessment_item.delete', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $promotionAssessmentId = $request->integer(
            'promotion_assessment_id',
        );

        $criterionType = trim(
            (string) $request->query('criterion_type', ''),
        );

        $isPassed = $request->has('is_passed')
            ? $request->boolean('is_passed')
            : null;

        $items = $this->promotionAssessmentItemService->paginate(
            perPage: $perPage,
            promotionAssessmentId: $promotionAssessmentId > 0
                ? $promotionAssessmentId
                : null,
            criterionType: $criterionType !== ''
                ? $criterionType
                : null,
            isPassed: $isPassed,
        );

        return ApiResponse::success(
            data: PromotionAssessmentItemResource::collection(
                $items,
            ),
            message: 'Daftar promotion assessment item berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePromotionAssessmentItemRequest $request): JsonResponse
    {
        $item = $this->promotionAssessmentItemService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: PromotionAssessmentItemResource::make($item),
            message: 'Promotion assessment item berhasil ditambahkan.',
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(PromotionAssessmentItem $promotionAssessmentItem): JsonResponse
    {
        $item = $this->promotionAssessmentItemService->findById($promotionAssessmentItem->id);

        return ApiResponse::success(
            data: PromotionAssessmentItemResource::make($item),
            message: 'Promotion assessment item berhasil diambil.',
        );
    }

    public function byAssessment(int $promotionAssessmentId): JsonResponse
    {
        $items = $this->promotionAssessmentItemService->findByPromotionAssessmentId($promotionAssessmentId);

        return ApiResponse::success(
            data: PromotionAssessmentItemResource::collection($items),
            message: 'Daftar item promotion assessment berhasil diambil.',
        );
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdatePromotionAssessmentItemRequest $request,
        PromotionAssessmentItem $promotionAssessmentItem
    ): JsonResponse {
        $item = $this->promotionAssessmentItemService->update(
            $promotionAssessmentItem,
            $request->validated(),
        );

        return ApiResponse::success(
            data: PromotionAssessmentItemResource::make($item),
            message: 'Promotion assessment item berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(
        PromotionAssessmentItem $promotionAssessmentItem,
    ): JsonResponse {
        $this->promotionAssessmentItemService->delete(
            $promotionAssessmentItem,
        );

        return ApiResponse::success(
            data: null,
            message: 'Promotion assessment item berhasil dihapus.',
        );
    }
}
