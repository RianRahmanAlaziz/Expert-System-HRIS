<?php

namespace App\Http\Controllers\Api\V1\Performance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Performance\PerformanceReviewIndexRequest;
use App\Http\Requests\Performance\StorePerformanceReviewRequest;
use App\Http\Requests\Performance\UpdatePerformanceReviewRequest;
use App\Http\Resources\V1\Performance\PerformanceReviewResource;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PerformanceReviewController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PerformanceReviewService $performanceReviewService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:performance_review.view', only: ['index', 'show']),
            new Middleware('permission:performance_review.create', only: ['store']),
            new Middleware('permission:performance_review.update', only: ['update', 'calculate']),
            new Middleware('permission:performance_review.submit', only: ['submit']),
            new Middleware('permission:performance_review.approve', only: ['approve']),
            new Middleware('permission:performance_review.reject', only: ['reject']),
            new Middleware('permission:performance_review.delete', only: ['destroy']),
        ];
    }

    public function index(PerformanceReviewIndexRequest $request): JsonResponse
    {
        Gate::authorize(
            'viewAny',
            PerformanceReview::class,
        );

        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $search = trim(
            (string) $request->query('search', ''),
        );

        $employeeId = $request->integer('employee_id');
        $employeeId = $employeeId > 0 ? $employeeId : null;

        $performancePeriodId = $request->integer(
            'performance_period_id',
        );
        $performancePeriodId = $performancePeriodId > 0
            ? $performancePeriodId
            : null;

        $reviews = $this->performanceReviewService->paginate(
            user: $request->user(),
            perPage: $perPage,
            search: $search,
            employeeId: $employeeId,
            performancePeriodId: $performancePeriodId,
            reviewType: $request->query('review_type'),
            status: $request->query('status'),
        );

        return ApiResponse::success(
            data: PerformanceReviewResource::collection($reviews),
            message: 'Daftar Performance review berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'from' => $reviews->firstItem(),
                    'to' => $reviews->lastItem(),
                ],
            ],
        );
    }

    public function store(
        StorePerformanceReviewRequest $request
    ): JsonResponse {
        Gate::authorize(
            'create',
            PerformanceReview::class
        );
        $review = $this->performanceReviewService->create(
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceReviewResource($review),
            message: 'Performance review berhasil dibuat.',
            status: 201,
        );
    }

    public function show(
        PerformanceReview $performanceReview
    ): JsonResponse {

        Gate::authorize(
            'view',
            $performanceReview
        );

        $review = $this->performanceReviewService->getById(
            $performanceReview->id,
        );

        return ApiResponse::success(
            data: new PerformanceReviewResource($review),
            message: 'Performance review berhasil diambil.',
        );
    }

    public function update(
        UpdatePerformanceReviewRequest $request,
        PerformanceReview $performanceReview
    ): JsonResponse {

        Gate::authorize(
            'update',
            $performanceReview
        );

        $review = $this->performanceReviewService->update(
            $request->user(),
            $performanceReview,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new PerformanceReviewResource($review),
            message: 'Performance review berhasil diperbarui.',
        );
    }

    public function calculate(
        Request $request,
        PerformanceReview $performanceReview
    ): JsonResponse {

        Gate::authorize(
            'update',
            $performanceReview
        );


        $review = $this->performanceReviewService->calculateScore(
            $request->user(),
            $performanceReview,
        );

        return ApiResponse::success(
            data: new PerformanceReviewResource($review),
            message: 'Performance score berhasil dihitung.',
        );
    }

    public function submit(
        Request $request,
        PerformanceReview $performanceReview
    ): JsonResponse {

        Gate::authorize(
            'submit',
            $performanceReview
        );

        $review = $this->performanceReviewService->submit(
            $request->user(),
            $performanceReview,
        );

        return ApiResponse::success(
            data: new PerformanceReviewResource($review),
            message: 'Performance review berhasil disubmit.',
        );
    }

    public function approve(
        Request $request,
        PerformanceReview $performanceReview
    ): JsonResponse {
        Gate::authorize(
            'approve',
            $performanceReview
        );

        $review = $this->performanceReviewService->approve(
            $request->user(),
            $performanceReview,
        );

        return ApiResponse::success(
            data: new PerformanceReviewResource($review),
            message: 'Performance review berhasil disetujui.',
        );
    }

    public function reject(
        Request $request,
        PerformanceReview $performanceReview
    ): JsonResponse {
        Gate::authorize(
            'reject',
            $performanceReview
        );
        $review = $this->performanceReviewService->reject(
            $request->user(),
            $performanceReview,
        );

        return ApiResponse::success(
            data: new PerformanceReviewResource($review),
            message: 'Performance review berhasil ditolak.',
        );
    }

    public function destroy(
        Request $request,
        PerformanceReview $performanceReview
    ): JsonResponse {
        Gate::authorize(
            'delete',
            $performanceReview
        );
        $this->performanceReviewService->delete(
            $request->user(),
            $performanceReview,
        );

        return ApiResponse::success(
            data: null,
            message: 'Performance review berhasil dihapus.',
        );
    }
}
