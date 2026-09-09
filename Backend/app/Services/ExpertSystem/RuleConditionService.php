<?php

namespace App\Services\ExpertSystem;

use App\Models\RuleCondition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RuleConditionService
{
    public function paginate(int $perPage = 15, ?int $expertRuleId = null,): LengthAwarePaginator
    {
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
            return $condition->load('expertRule');
        },);
    }
    public function update(RuleCondition $ruleCondition, array $data,): RuleCondition
    {
        DB::transaction(function () use ($ruleCondition, $data): void {
            $ruleCondition->update($data);
        });
        return $ruleCondition->refresh()->load('expertRule');
    }
    public function delete(RuleCondition $ruleCondition): void
    {
        DB::transaction(static function () use ($ruleCondition): void {
            $ruleCondition->delete();
        });
    }
}
