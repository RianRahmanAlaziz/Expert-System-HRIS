<?php

namespace App\Http\Controllers\Api\V1\Position;

use App\Http\Controllers\Controller;
use App\Http\Requests\Position\StorePositionRequirementCompetencyRequest;
use App\Http\Requests\Position\UpdatePositionRequirementCompetencyRequest;
use App\Http\Resources\V1\Position\PositionRequirementCompetencyResource;
use App\Models\PositionRequirementCompetency;
use App\Services\Position\PositionRequirementCompetencyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PositionRequirementCompetencyController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PositionRequirementCompetencyService $positionRequirementCompetencyService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:position_requirement_competency.view',  only: ['index', 'show', 'byRequirement']),
            new Middleware('permission:position_requirement_competency.create',  only: ['store']),
            new Middleware('permission:position_requirement_competency.update',   only: ['update']),
            new Middleware('permission:position_requirement_competency.delete',   only: ['destroy']),
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

        $positionRequirementId = $request->integer(
            'position_requirement_id',
        );

        $competencyId = $request->integer(
            'competency_id',
        );

        $isRequired = $request->has('is_required')
            ? $request->boolean('is_required')
            : null;

        $positionRequirementCompetencies =
            $this->positionRequirementCompetencyService->paginate(
                perPage: $perPage,
                positionRequirementId: $positionRequirementId > 0
                    ? $positionRequirementId
                    : null,
                competencyId: $competencyId > 0
                    ? $competencyId
                    : null,
                isRequired: $isRequired,
            );

        return ApiResponse::success(
            data: PositionRequirementCompetencyResource::collection(
                $positionRequirementCompetencies,
            ),
            message: 'Daftar position requirement competency berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $positionRequirementCompetencies->currentPage(),
                    'last_page' => $positionRequirementCompetencies->lastPage(),
                    'per_page' => $positionRequirementCompetencies->perPage(),
                    'total' => $positionRequirementCompetencies->total(),
                    'from' => $positionRequirementCompetencies->firstItem(),
                    'to' => $positionRequirementCompetencies->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StorePositionRequirementCompetencyRequest $request,
    ): JsonResponse {
        $positionRequirementCompetency =
            $this->positionRequirementCompetencyService->create(
                $request->validated(),
            );

        return ApiResponse::success(
            data: PositionRequirementCompetencyResource::make(
                $positionRequirementCompetency,
            ),
            message: 'Position requirement competency berhasil dibuat.',
            status: 201,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(
        PositionRequirementCompetency $positionRequirementCompetency,
    ): JsonResponse {
        $positionRequirementCompetency =
            $this->positionRequirementCompetencyService->findById(
                $positionRequirementCompetency->id,
            );

        return ApiResponse::success(
            data: PositionRequirementCompetencyResource::make(
                $positionRequirementCompetency,
            ),
            message: 'Detail position requirement competency berhasil diambil.',
        );
    }

    public function byRequirement(
        int $positionRequirementId,
    ): JsonResponse {
        $positionRequirementCompetencies =
            $this->positionRequirementCompetencyService
            ->getByPositionRequirement(
                $positionRequirementId,
            );

        return ApiResponse::success(
            data: PositionRequirementCompetencyResource::collection(
                $positionRequirementCompetencies,
            ),
            message: 'Daftar competency requirement berhasil diambil.',
        );
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdatePositionRequirementCompetencyRequest $request,
        PositionRequirementCompetency $positionRequirementCompetency,
    ): JsonResponse {
        $positionRequirementCompetency =
            $this->positionRequirementCompetencyService->update(
                $positionRequirementCompetency,
                $request->validated(),
            );

        return ApiResponse::success(
            data: PositionRequirementCompetencyResource::make(
                $positionRequirementCompetency,
            ),
            message: 'Position requirement competency berhasil diperbarui.',
        );
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(
        PositionRequirementCompetency $positionRequirementCompetency,
    ): JsonResponse {
        $this->positionRequirementCompetencyService->delete(
            $positionRequirementCompetency,
        );

        return ApiResponse::success(
            data: null,
            message: 'Position requirement competency berhasil dihapus.',
        );
    }
}
