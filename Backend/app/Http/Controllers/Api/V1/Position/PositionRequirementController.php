<?php

namespace App\Http\Controllers\Api\V1\Position;

use App\Http\Controllers\Controller;
use App\Http\Requests\Position\StorePositionRequirementRequest;
use App\Http\Requests\Position\UpdatePositionRequirementRequest;
use App\Http\Resources\V1\Position\PositionRequirementResource;
use App\Models\PositionRequirement;
use App\Services\Position\PositionRequirementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PositionRequirementController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PositionRequirementService $positionRequirementService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:position_requirement.view', only: ['index', 'show', 'byPosition'],),
            new Middleware('permission:position_requirement.create', only: ['store'],),
            new Middleware('permission:position_requirement.update', only: ['update'],),
            new Middleware('permission:position_requirement.delete', only: ['destroy'],),
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

        $positionId = $request->integer('position_id');

        $positionRequirements = $this->positionRequirementService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            positionId: $positionId > 0 ? $positionId : null,
        );

        return ApiResponse::success(
            data: PositionRequirementResource::collection(
                $positionRequirements,
            ),
            message: 'Daftar position requirement berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $positionRequirements->currentPage(),
                    'last_page' => $positionRequirements->lastPage(),
                    'per_page' => $positionRequirements->perPage(),
                    'total' => $positionRequirements->total(),
                    'from' => $positionRequirements->firstItem(),
                    'to' => $positionRequirements->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StorePositionRequirementRequest $request,
    ): JsonResponse {
        $positionRequirement = $this->positionRequirementService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: PositionRequirementResource::make(
                $positionRequirement,
            ),
            message: 'Position requirement berhasil dibuat.',
            status: 201,
        );
    }
    /**
     * Display the specified resource.
     */
    public function show(
        PositionRequirement $positionRequirement,
    ): JsonResponse {
        $positionRequirement = $this->positionRequirementService->findById(
            $positionRequirement->id,
        );

        return ApiResponse::success(
            data: PositionRequirementResource::make(
                $positionRequirement,
            ),
            message: 'Detail position requirement berhasil diambil.',
        );
    }

    public function byPosition(int $positionId): JsonResponse
    {
        $positionRequirement = $this->positionRequirementService
            ->findByPositionId($positionId);

        return ApiResponse::success(
            data: $positionRequirement
                ? PositionRequirementResource::make($positionRequirement)
                : null,
            message: $positionRequirement
                ? 'Position requirement berhasil diambil.'
                : 'Position belum memiliki requirement aktif.',
        );
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdatePositionRequirementRequest $request,
        PositionRequirement $positionRequirement,
    ): JsonResponse {
        $positionRequirement = $this->positionRequirementService->update(
            $positionRequirement,
            $request->validated(),
        );

        return ApiResponse::success(
            data: PositionRequirementResource::make(
                $positionRequirement,
            ),
            message: 'Position requirement berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(
        PositionRequirement $positionRequirement,
    ): JsonResponse {
        $this->positionRequirementService->delete(
            $positionRequirement,
        );

        return ApiResponse::success(
            data: null,
            message: 'Position requirement berhasil dihapus.',
        );
    }
}
