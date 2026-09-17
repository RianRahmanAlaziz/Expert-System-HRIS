<?php

namespace App\Services\ExpertSystem;

use App\Models\RuleCondition;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RuleConditionService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?int $expertRuleId = null
    ): LengthAwarePaginator {
        return RuleCondition::query()
            ->with('expertRule')
            ->when($expertRuleId !== null, function ($query) use ($expertRuleId): void {
                $query->where('expert_rule_id', $expertRuleId);
            },)->orderBy('sort_order')->latest()->paginate($perPage);
    }

    public function findById(int $id): RuleCondition
    {
        return RuleCondition::query()->with('expertRule')->findOrFail($id);
    }

    public function create(array $data): RuleCondition
    {
        return DB::transaction(function () use ($data): RuleCondition {
            $condition = RuleCondition::query()->create([
                'expert_rule_id' => $data['expert_rule_id'],
                'parameter' => $data['parameter'],
                'operator' => $data['operator'],
                'value' => $data['value'],
                'logical_operator' => $data['logical_operator'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->activityLogService->log(
                action: 'create',
                module: 'rule_condition',
                target: $condition,
                newValues: $condition->toArray(),
            );

            return $condition->load('expertRule');
        });
    }

    public function update(RuleCondition $ruleCondition, array $data): RuleCondition
    {
        DB::transaction(function () use ($ruleCondition, $data): void {
            $oldValues = $ruleCondition->toArray();
            $ruleCondition->update($data);
            $ruleCondition->refresh();

            $this->activityLogService->log(
                action: 'update',
                module: 'rule_condition',
                target: $ruleCondition,
                oldValues: $oldValues,
                newValues: $ruleCondition->toArray(),
            );
        });

        return $ruleCondition->refresh()->load('expertRule');
    }

    public function delete(RuleCondition $ruleCondition): void
    {
        DB::transaction(function () use ($ruleCondition): void {
            $oldValues = $ruleCondition->toArray();
            $this->activityLogService->log(
                action: 'delete',
                module: 'rule_condition',
                target: $ruleCondition,
                oldValues: $oldValues,
            );

            $ruleCondition->delete();
        });
    }
}
