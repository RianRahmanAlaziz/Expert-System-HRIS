<?php

namespace App\Http\Controllers\Api\V1\ExpertSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpertSystem\Knowledge\StoreKnowledgeRequest;
use App\Http\Requests\ExpertSystem\Knowledge\UpdateKnowledgeRequest;
use App\Http\Resources\V1\ExpertSystem\KnowledgeResource;
use App\Models\Knowledge;
use App\Services\ExpertSystem\KnowledgeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class KnowledgeController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly KnowledgeService $knowledgeService
    ) {}
    public static function middleware(): array
    {
        return [
            new Middleware('permission:knowledge.view', only: ['index', 'show']),
            new Middleware('permission:knowledge.create', only: ['store']),
            new Middleware('permission:knowledge.update', only: ['update']),
            new Middleware('permission:knowledge.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100,);

        $search = trim((string) $request->query('search', ''),);

        $knowledgeCategoryId = $request->integer('knowledge_category_id');

        $knowledge = $this->knowledgeService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            knowledgeCategoryId: $knowledgeCategoryId > 0 ? $knowledgeCategoryId : null,
        );
        return ApiResponse::success(
            data: KnowledgeResource::collection($knowledge),
            message: 'Daftar knowledge berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $knowledge->currentPage(),
                    'last_page' => $knowledge->lastPage(),
                    'per_page' => $knowledge->perPage(),
                    'total' => $knowledge->total(),
                    'from' => $knowledge->firstItem(),
                    'to' => $knowledge->lastItem(),
                ],
            ],
        );
    }

    public function store(StoreKnowledgeRequest $request): JsonResponse
    {
        $knowledge = $this->knowledgeService->create(
            $request->validated()
        );
        return ApiResponse::success(
            data: KnowledgeResource::make($knowledge),
            message: 'Knowledge berhasil dibuat.',
        );
    }

    public function show(Knowledge $knowledge): JsonResponse
    {
        $knowledge = $this->knowledgeService->findById($knowledge->id);

        return ApiResponse::success(
            data: KnowledgeResource::make($knowledge),
            message: 'Detail knowledge berhasil diambil.',
        );
    }

    public function update(
        UpdateKnowledgeRequest $request,
        Knowledge $knowledge,
    ): JsonResponse {
        $knowledge = $this->knowledgeService->update(
            $knowledge,
            $request->validated(),
        );
        return ApiResponse::success(
            data: KnowledgeResource::make($knowledge),
            message: 'Knowledge berhasil diperbarui.',
        );
    }

    public function destroy(Knowledge $knowledge): JsonResponse
    {
        $this->knowledgeService->delete($knowledge);
        return ApiResponse::success(
            data: null,
            message: 'Knowledge berhasil dihapus.',
        );
    }
}
