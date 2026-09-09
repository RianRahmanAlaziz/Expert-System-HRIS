<?php

namespace App\Http\Controllers\Api\V1\ExpertSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpertSystem\KnowledgeCategory\StoreKnowledgeCategoryRequest;
use App\Http\Requests\ExpertSystem\KnowledgeCategory\UpdateKnowledgeCategoryRequest;
use App\Http\Resources\V1\ExpertSystem\KnowledgeCategoryResource;
use App\Models\KnowledgeCategory;
use App\Services\ExpertSystem\KnowledgeCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class KnowledgeCategoryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly KnowledgeCategoryService $knowledgeCategoryService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:knowledge_category.view', only: ['index', 'show']),
            new Middleware('permission:knowledge_category.create', only: ['store']),
            new Middleware('permission:knowledge_category.update', only: ['update']),
            new Middleware('permission:knowledge_category.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100,);

        $search = trim((string) $request->query('search', ''),);

        $knowledgeCategories = $this->knowledgeCategoryService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
        );
        return ApiResponse::success(
            data: KnowledgeCategoryResource::collection($knowledgeCategories),
            message: 'Daftar knowledge category berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $knowledgeCategories->currentPage(),
                    'last_page' => $knowledgeCategories->lastPage(),
                    'per_page' => $knowledgeCategories->perPage(),
                    'total' => $knowledgeCategories->total(),
                    'from' => $knowledgeCategories->firstItem(),
                    'to' => $knowledgeCategories->lastItem(),
                ],
            ],
        );
    }

    public function store(StoreKnowledgeCategoryRequest $request): JsonResponse
    {
        $knowledgeCategory = $this->knowledgeCategoryService->create(
            $request->validated()
        );

        return ApiResponse::success(
            data: KnowledgeCategoryResource::make($knowledgeCategory),
            message: 'Knowledge category berhasil dibuat.',
        );
    }

    public function show(KnowledgeCategory $knowledgeCategory): JsonResponse
    {
        $knowledgeCategory = $this->knowledgeCategoryService->findById($knowledgeCategory->id);

        return ApiResponse::success(
            data: KnowledgeCategoryResource::make($knowledgeCategory),
            message: 'Detail knowledge category berhasil diambil.',
        );
    }

    public function update(
        UpdateKnowledgeCategoryRequest $request,
        KnowledgeCategory $knowledgeCategory,
    ): JsonResponse {
        $knowledgeCategory = $this->knowledgeCategoryService->update(
            $knowledgeCategory,
            $request->validated(),
        );
        return ApiResponse::success(
            data: KnowledgeCategoryResource::make($knowledgeCategory),
            message: 'Knowledge category berhasil diperbarui.',
        );
    }

    public function destroy(KnowledgeCategory $knowledgeCategory): JsonResponse
    {
        $this->knowledgeCategoryService->delete($knowledgeCategory);
        return ApiResponse::success(
            data: null,
            message: 'Knowledge category berhasil dihapus.',
        );
    }
}
