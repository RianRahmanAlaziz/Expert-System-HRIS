<?php

namespace App\Http\Controllers\Api\V1\Recommendation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recommendation\StoreRecommendationRequest;
use App\Http\Requests\Recommendation\UpdateRecommendationStatusRequest;
use App\Http\Resources\V1\Recommendation\RecommendationResource;
use App\Models\ExpertConsultation;
use App\Models\Recommendation;
use App\Services\Recommendation\RecommendationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RecommendationController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly RecommendationService $recommendationService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:recommendation.view', only: ['index', 'show']),
            new Middleware('permission:recommendation.create', only: ['store']),
            new Middleware('permission:recommendation.update', only: ['updateStatus']),
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

        $search = trim(
            (string) $request->query('search', ''),
        );

        $employeeId = $request->integer('employee_id');

        $type = trim(
            (string) $request->query('type', ''),
        );

        $status = trim(
            (string) $request->query('status', ''),
        );

        $recommendations = $this->recommendationService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            employeeId: $employeeId > 0 ? $employeeId : null,
            type: $type !== '' ? $type : null,
            status: $status !== '' ? $status : null,
        );

        return ApiResponse::success(
            data: RecommendationResource::collection($recommendations),
            message: 'Daftar recommendation berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $recommendations->currentPage(),
                    'last_page' => $recommendations->lastPage(),
                    'per_page' => $recommendations->perPage(),
                    'total' => $recommendations->total(),
                    'from' => $recommendations->firstItem(),
                    'to' => $recommendations->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRecommendationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $consultation = ExpertConsultation::query()->findOrFail($data['expert_consultation_id']);

        $recommendation = $this->recommendationService->createFromConsultation(
            consultation: $consultation,
            data: $data,
        );

        return ApiResponse::success(
            data: RecommendationResource::make(
                $recommendation->load([
                    'employee',
                    'expertConsultation',
                    'histories.user',
                ]),
            ),
            message: 'Recommendation berhasil dibuat.',
            status: 201,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Recommendation $recommendation): JsonResponse
    {
        $recommendation = $this->recommendationService->findById(
            $recommendation->id,
        );

        return ApiResponse::success(
            data: RecommendationResource::make(
                $recommendation,
            ),
            message: 'Detail recommendation berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(
        UpdateRecommendationStatusRequest $request,
        Recommendation $recommendation,
    ): JsonResponse {
        $data = $request->validated();

        $recommendation = $this->recommendationService->updateStatus(
            recommendation: $recommendation,
            status: $data['status'],
            notes: $data['notes'] ?? null,
            userId: $request->user()->id,
        );

        return ApiResponse::success(
            data: RecommendationResource::make($recommendation),
            message: 'Status recommendation berhasil diperbarui.',
        );
    }
}
