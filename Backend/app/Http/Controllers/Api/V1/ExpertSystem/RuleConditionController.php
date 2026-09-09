<?php

namespace App\Http\Controllers\Api\V1\ExpertSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpertSystem\RuleCondition\StoreRuleConditionRequest;
use App\Http\Requests\ExpertSystem\RuleCondition\UpdateRuleConditionRequest;
use App\Http\Resources\V1\ExpertSystem\RuleConditionResource;
use App\Models\RuleCondition;
use App\Services\ExpertSystem\RuleConditionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RuleConditionController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly RuleConditionService $ruleConditionService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:rule_condition.view', only: ['index', 'show']),
            new Middleware('permission:rule_condition.create', only: ['store']),
            new Middleware('permission:rule_condition.update', only: ['update']),
            new Middleware('permission:rule_condition.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100,);
        $expertRuleId = $request->integer('expert_rule_id');

        $ruleConditions = $this->ruleConditionService->paginate(
            perPage: $perPage,
            expertRuleId: $expertRuleId > 0 ? $expertRuleId : null,
        );

        return ApiResponse::success(
            data: RuleConditionResource::collection($ruleConditions),
            message: 'Daftar rule condition berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $ruleConditions->currentPage(),
                    'last_page' => $ruleConditions->lastPage(),
                    'per_page' => $ruleConditions->perPage(),
                    'total' => $ruleConditions->total(),
                    'from' => $ruleConditions->firstItem(),
                    'to' => $ruleConditions->lastItem(),
                ],
            ],
        );
    }

    public function store(StoreRuleConditionRequest $request): JsonResponse
    {
        $ruleCondition = $this->ruleConditionService->create(
            $request->validated()
        );
        return ApiResponse::success(
            data: RuleConditionResource::make($ruleCondition),
            message: 'Rule condition berhasil dibuat.',
        );
    }

    public function show(RuleCondition $ruleCondition): JsonResponse
    {
        $ruleCondition = $this->ruleConditionService->findById(
            $ruleCondition->id
        );
        return ApiResponse::success(
            data: RuleConditionResource::make($ruleCondition),
            message: 'Detail rule condition berhasil diambil.',
        );
    }

    public function update(
        UpdateRuleConditionRequest $request,
        RuleCondition $ruleCondition,
    ): JsonResponse {
        $ruleCondition = $this->ruleConditionService->update(
            $ruleCondition,
            $request->validated(),
        );
        return ApiResponse::success(
            data: RuleConditionResource::make($ruleCondition),
            message: 'Rule condition berhasil diperbarui.',
        );
    }

    public function destroy(RuleCondition $ruleCondition): JsonResponse
    {
        $this->ruleConditionService->delete($ruleCondition);
        return ApiResponse::success(
            data: null,
            message: 'Rule condition berhasil dihapus.',
        );
    }
}
