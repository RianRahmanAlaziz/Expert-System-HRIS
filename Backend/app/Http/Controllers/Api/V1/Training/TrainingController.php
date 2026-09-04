<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreTrainingRequest;
use App\Http\Requests\Training\UpdateTrainingRequest;
use App\Http\Requests\Training\UpdateTrainingStatusRequest;
use App\Http\Resources\V1\Training\TrainingResource;
use App\Models\Training;
use App\Services\Training\TrainingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TrainingController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly TrainingService $trainingService

    ) {}
    public static function middleware(): array
    {
        return [
            new Middleware('permission:training.view', only: ['index', 'show']),
            new Middleware('permission:training.create', only: ['store']),
            new Middleware('permission:training.update', only: ['update']),
            new Middleware('permission:training.status.update', only: ['updateStatus']),
            new Middleware('permission:training.delete', only: ['destroy']),
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

        $search = trim((string) $request->query('search', ''));

        $trainings = $this->trainingService->paginate(
            perPage: $perPage,
            search: $search,
        );

        return ApiResponse::success(
            data: TrainingResource::collection($trainings),
            message: 'Daftar pelatihan berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $trainings->currentPage(),
                    'last_page' => $trainings->lastPage(),
                    'per_page' => $trainings->perPage(),
                    'total' => $trainings->total(),
                    'from' => $trainings->firstItem(),
                    'to' => $trainings->lastItem(),
                ],
            ],
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTrainingRequest $request): JsonResponse
    {
        $training = $this->trainingService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: TrainingResource::make($training),
            message: 'Pelatihan berhasil dibuat.',
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Training $training): JsonResponse
    {
        return ApiResponse::success(
            data: TrainingResource::make($training),
            message: 'Detail Pelatihan berhasil diambil.',
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrainingRequest $request, Training $training): JsonResponse
    {
        $training = $this->trainingService->update(
            $training,
            $request->validated()
        );
        return ApiResponse::success(
            data: TrainingResource::make($training),
            message: 'Pelatihan berhasil diperbarui.',
        );
    }

    public function updateStatus(
        UpdateTrainingStatusRequest $request,
        Training $training
    ): JsonResponse {
        $training = $this->trainingService->updateStatus(
            $training,
            $request->validated('status'),
        );

        return ApiResponse::success(
            data: TrainingResource::make($training),
            message: 'Status training berhasil diperbarui.',
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Training $training): JsonResponse
    {
        $this->trainingService->delete($training);

        return ApiResponse::success(
            data: null,
            message: 'Pelatihan berhasil dihapus.',
        );
    }
}
