<?php

namespace App\Http\Controllers\Api\V1\Promotion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\StorePromotionAssessmentRequest;
use App\Http\Requests\Promotion\UpdatePromotionAssessmentRequest;
use App\Http\Resources\V1\Promotion\PromotionAssessmentResource;
use App\Models\PromotionAssessment;
use App\Services\Promotion\PromotionAssessmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;


class PromotionAssessmentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PromotionAssessmentService $promotionAssessmentService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:promotion_assessment.view',     only: ['index', 'show']),
            new Middleware('permission:promotion_assessment.create',    only: ['store']),
            new Middleware('permission:promotion_assessment.update', only: ['update'],),
            new Middleware('permission:promotion_assessment.delete',  only: ['destroy']),
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

        $employeeId = $request->integer('employee_id');

        $currentPositionId = $request->integer(
            'current_position_id',
        );

        $targetPositionId = $request->integer(
            'target_position_id',
        );

        $assessedBy = $request->integer('assessed_by');

        $status = trim(
            (string) $request->query('status', ''),
        );

        $assessments = $this->promotionAssessmentService->paginate(
            perPage: $perPage,
            employeeId: $employeeId > 0 ? $employeeId : null,
            currentPositionId: $currentPositionId > 0
                ? $currentPositionId
                : null,
            targetPositionId: $targetPositionId > 0
                ? $targetPositionId
                : null,
            assessedBy: $assessedBy > 0 ? $assessedBy : null,
            status: $status !== '' ? $status : null,
        );

        return ApiResponse::success(
            data: PromotionAssessmentResource::collection(
                $assessments,
            ),
            message: 'Daftar promotion assessment berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $assessments->currentPage(),
                    'last_page' => $assessments->lastPage(),
                    'per_page' => $assessments->perPage(),
                    'total' => $assessments->total(),
                    'from' => $assessments->firstItem(),
                    'to' => $assessments->lastItem(),
                ],
            ],
        );
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StorePromotionAssessmentRequest $request,
    ): JsonResponse {
        $assessment = $this->promotionAssessmentService->create(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(
            data: PromotionAssessmentResource::make($assessment),
            message: 'Promotion assessment berhasil dibuat.',
        );
    }


    /**
     * Display the specified resource.
     */
    public function show(PromotionAssessment $promotionAssessment): JsonResponse
    {
        $assessment = $this->promotionAssessmentService->findById(
            $promotionAssessment->id,
        );

        return ApiResponse::success(
            data: PromotionAssessmentResource::make($assessment),
            message: 'Detail promotion assessment berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdatePromotionAssessmentRequest $request,
        PromotionAssessment $promotionAssessment,
    ): JsonResponse {
        $assessment = $this->promotionAssessmentService->update(
            $promotionAssessment,
            $request->validated(),
        );

        return ApiResponse::success(
            data: PromotionAssessmentResource::make($assessment),
            message: 'Promotion assessment berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(
        PromotionAssessment $promotionAssessment,
    ): JsonResponse {
        $this->promotionAssessmentService->delete($promotionAssessment);
        return ApiResponse::success(
            data: null,
            message: 'Promotion assessment berhasil dihapus.',
        );
    }
}
