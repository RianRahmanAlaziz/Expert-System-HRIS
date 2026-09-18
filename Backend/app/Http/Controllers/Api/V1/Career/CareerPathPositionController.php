<?php

namespace App\Http\Controllers\Api\V1\Career;

use App\Http\Controllers\Controller;
use App\Http\Requests\Career\StoreCareerPathPositionRequest;
use App\Http\Requests\Career\UpdateCareerPathPositionRequest;
use App\Http\Resources\V1\Career\CareerPathPositionResource;
use App\Models\CareerPath;
use App\Models\CareerPathPosition;
use App\Services\Career\CareerPathPositionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CareerPathPositionController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CareerPathPositionService $careerPathPositionService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:career_path_position.view', only: ['index', 'show', 'byCareerPath']),
            new Middleware('permission:career_path_position.create', only: ['store']),
            new Middleware('permission:career_path_position.update',  only: ['update']),
            new Middleware('permission:career_path_position.delete',  only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $careerPathId = $request->integer('career_path_id');

        $positionId = $request->integer('position_id');

        $careerPathPositions = $this->careerPathPositionService->paginate(
            perPage: $perPage,
            careerPathId: $careerPathId > 0 ? $careerPathId : null,
            positionId: $positionId > 0 ? $positionId : null,
        );

        return ApiResponse::success(
            data: CareerPathPositionResource::collection(
                $careerPathPositions,
            ),
            message: 'Daftar career path position berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $careerPathPositions->currentPage(),
                    'last_page' => $careerPathPositions->lastPage(),
                    'per_page' => $careerPathPositions->perPage(),
                    'total' => $careerPathPositions->total(),
                    'from' => $careerPathPositions->firstItem(),
                    'to' => $careerPathPositions->lastItem(),
                ],
            ],
        );
    }

    public function store(
        StoreCareerPathPositionRequest $request,
    ): JsonResponse {
        $careerPathPosition = $this->careerPathPositionService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: new CareerPathPositionResource($careerPathPosition),
            message: 'Position pada career path berhasil ditambahkan.',
            status: 201,
        );
    }

    public function show(
        CareerPathPosition $careerPathPosition,
    ): JsonResponse {
        $careerPathPosition = $this->careerPathPositionService->findById(
            $careerPathPosition->id,
        );

        return ApiResponse::success(
            data: new CareerPathPositionResource(
                $careerPathPosition,
            ),
            message: 'Career path position berhasil diambil.',
        );
    }

    public function byCareerPath(
        CareerPath $careerPath,
    ): JsonResponse {
        $careerPathPositions = $this->careerPathPositionService
            ->findByCareerPathId($careerPath->id);

        return ApiResponse::success(
            data: CareerPathPositionResource::collection($careerPathPositions),
            message: 'Daftar position pada career path berhasil diambil.',
        );
    }

    public function update(
        UpdateCareerPathPositionRequest $request,
        CareerPathPosition $careerPathPosition,
    ): JsonResponse {
        $careerPathPosition = $this->careerPathPositionService->update(
            $careerPathPosition,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new CareerPathPositionResource(
                $careerPathPosition,
            ),
            message: 'Career path position berhasil diperbarui.',
        );
    }

    public function destroy(
        CareerPathPosition $careerPathPosition,
    ): JsonResponse {
        $this->careerPathPositionService->delete(
            $careerPathPosition,
        );

        return ApiResponse::success(
            data: null,
            message: 'Career path position berhasil dihapus.',
        );
    }
}
