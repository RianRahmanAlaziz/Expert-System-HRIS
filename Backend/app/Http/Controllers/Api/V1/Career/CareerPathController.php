<?php

namespace App\Http\Controllers\Api\V1\Career;

use App\Http\Controllers\Controller;
use App\Http\Requests\Career\StoreCareerPathRequest;
use App\Http\Requests\Career\UpdateCareerPathRequest;
use App\Http\Resources\V1\Career\CareerPathResource;
use App\Models\CareerPath;
use App\Services\Career\CareerPathService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CareerPathController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly CareerPathService $careerPathService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:career_path.view', only: ['index', 'show']),
            new Middleware('permission:career_path.create', only: ['store']),
            new Middleware('permission:career_path.update', only: ['update']),
            new Middleware('permission:career_path.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $search = trim(
            (string) $request->query('search', ''),
        );

        $status = $request->query('status');

        $isActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : null;

        $careerPaths = $this->careerPathService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            status: $status !== null ? (string) $status : null,
            isActive: $isActive,
        );

        return ApiResponse::success(
            data: CareerPathResource::collection(
                $careerPaths,
            ),
            message: 'Daftar career path berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $careerPaths->currentPage(),
                    'last_page' => $careerPaths->lastPage(),
                    'per_page' => $careerPaths->perPage(),
                    'total' => $careerPaths->total(),
                    'from' => $careerPaths->firstItem(),
                    'to' => $careerPaths->lastItem(),
                ],
            ],
        );
    }

    public function store(
        StoreCareerPathRequest $request,
    ): JsonResponse {
        $careerPath = $this->careerPathService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: new CareerPathResource($careerPath),
            message: 'Career path berhasil dibuat.',
            status: 201,
        );
    }

    public function show(CareerPath $careerPath): JsonResponse
    {
        $careerPath = $this->careerPathService->findById(
            $careerPath->id,
        );

        return ApiResponse::success(
            data: new CareerPathResource($careerPath),
            message: 'Career path berhasil diambil.',
        );
    }

    public function update(
        UpdateCareerPathRequest $request,
        CareerPath $careerPath,
    ): JsonResponse {
        $careerPath = $this->careerPathService->update(
            $careerPath,
            $request->validated(),
        );

        return ApiResponse::success(
            data: new CareerPathResource($careerPath),
            message: 'Career path berhasil diperbarui.',
        );
    }

    public function destroy(CareerPath $careerPath): JsonResponse
    {
        $this->careerPathService->delete(
            $careerPath,
        );

        return ApiResponse::success(
            data: null,
            message: 'Career path berhasil dihapus.',
        );
    }
}
