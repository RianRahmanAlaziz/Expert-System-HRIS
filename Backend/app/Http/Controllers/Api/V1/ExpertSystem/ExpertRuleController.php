<?php

namespace App\Http\Controllers\Api\V1\ExpertSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpertSystem\ExpertRule\StoreExpertRuleRequest;
use App\Http\Requests\ExpertSystem\ExpertRule\UpdateExpertRuleRequest;
use App\Http\Resources\V1\ExpertSystem\ExpertRuleResource;
use App\Models\ExpertRule;
use App\Services\ExpertSystem\ExpertRuleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExpertRuleController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ExpertRuleService $expertRuleService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:expert_rule.view', only: ['index', 'show']),
            new Middleware('permission:expert_rule.create', only: ['store']),
            new Middleware('permission:expert_rule.update', only: ['update']),
            new Middleware('permission:expert_rule.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100,);
        $search = trim((string) $request->query('search', ''),);
        $knowledgeId = $request->integer('knowledge_id');

        $expertRules = $this->expertRuleService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            knowledgeId: $knowledgeId > 0 ? $knowledgeId : null,
        );

        return ApiResponse::success(
            data: ExpertRuleResource::collection($expertRules),
            message: 'Daftar expert rule berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $expertRules->currentPage(),
                    'last_page' => $expertRules->lastPage(),
                    'per_page' => $expertRules->perPage(),
                    'total' => $expertRules->total(),
                    'from' => $expertRules->firstItem(),
                    'to' => $expertRules->lastItem(),
                ],
            ],
        );
    }

    public function store(StoreExpertRuleRequest $request): JsonResponse
    {
        $expertRule = $this->expertRuleService->create(
            $request->validated(),
        );

        return ApiResponse::success(
            data: ExpertRuleResource::make($expertRule),
            message: 'Expert rule berhasil dibuat.',
            status: 201,
        );
    }

    public function show(ExpertRule $expertRule): JsonResponse
    {
        $expertRule = $this->expertRuleService->findById(
            $expertRule->id,
        );

        return ApiResponse::success(
            data: ExpertRuleResource::make($expertRule),
            message: 'Detail expert rule berhasil diambil.',
        );
    }

    public function update(
        UpdateExpertRuleRequest $request,
        ExpertRule $expertRule,
    ): JsonResponse {
        $expertRule = $this->expertRuleService->update(
            $expertRule,
            $request->validated(),
        );
        return ApiResponse::success(
            data: ExpertRuleResource::make($expertRule),
            message: 'Expert rule berhasil diperbarui.',
        );
    }

    public function destroy(ExpertRule $expertRule): JsonResponse
    {
        $this->expertRuleService->delete($expertRule);
        return ApiResponse::success(
            data: null,
            message: 'Expert rule berhasil dihapus.',
        );
    }
}
