<?php

namespace App\Http\Controllers\Api\V1\ExpertSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpertSystem\RuleAction\StoreRuleActionRequest;
use App\Http\Requests\ExpertSystem\RuleAction\UpdateRuleActionRequest;
use App\Http\Resources\V1\ExpertSystem\RuleActionResource;
use App\Models\RuleAction;
use App\Services\ExpertSystem\RuleActionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RuleActionController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly RuleActionService $ruleActionService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:rule_action.view', only: ['index', 'show']),
            new Middleware('permission:rule_action.create', only: ['store']),
            new Middleware('permission:rule_action.update', only: ['update']),
            new Middleware('permission:rule_action.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100,);
        $expertRuleId = $request->integer('expert_rule_id');

        $ruleActions = $this->ruleActionService->paginate(
            perPage: $perPage,
            expertRuleId: $expertRuleId > 0 ? $expertRuleId : null,
        );
        return ApiResponse::success(
            data: RuleActionResource::collection($ruleActions),
            message: 'Daftar rule action berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $ruleActions->currentPage(),
                    'last_page' => $ruleActions->lastPage(),
                    'per_page' => $ruleActions->perPage(),
                    'total' => $ruleActions->total(),
                    'from' => $ruleActions->firstItem(),
                    'to' => $ruleActions->lastItem(),
                ],
            ],
        );
    }

    public function store(StoreRuleActionRequest $request): JsonResponse
    {
        $ruleAction = $this->ruleActionService->create(
            $request->validated(),
        );
        return ApiResponse::success(
            data: RuleActionResource::make($ruleAction),
            message: 'Rule action berhasil dibuat.',
        );
    }

    public function show(RuleAction $ruleAction): JsonResponse
    {
        $ruleAction = $this->ruleActionService->findById($ruleAction->id);
        return ApiResponse::success(
            data: RuleActionResource::make($ruleAction),
            message: 'Detail rule action berhasil diambil.',
        );
    }

    public function update(
        UpdateRuleActionRequest $request,
        RuleAction $ruleAction,
    ): JsonResponse {
        $ruleAction = $this->ruleActionService->update(
            $ruleAction,
            $request->validated(),
        );
        return ApiResponse::success(
            data: RuleActionResource::make($ruleAction),
            message: 'Rule action berhasil diperbarui.',
        );
    }

    public function destroy(RuleAction $ruleAction): JsonResponse
    {
        $this->ruleActionService->delete($ruleAction);
        return ApiResponse::success(
            data: null,
            message: 'Rule action berhasil dihapus.',
        );
    }
}
