<?php

namespace App\Services\ExpertSystem;

use App\Models\RuleAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RuleActionService
{
    public function paginate(
        int $perPage = 15,
        ?int $expertRuleId = null
    ): LengthAwarePaginator {
        return RuleAction::query()
            ->with('expertRule')
            ->when(
                $expertRuleId !== null,
                function ($query) use ($expertRuleId): void {
                    $query->where('expert_rule_id', $expertRuleId);
                },
            )->latest()->paginate($perPage);
    }
    public function findById(int $id): RuleAction
    {
        return RuleAction::query()->with('expertRule')->findOrFail($id);
    }
    public function create(array $data): RuleAction
    {
        return DB::transaction(function () use ($data): RuleAction {
            $action = RuleAction::query()->create([
                'expert_rule_id' => $data['expert_rule_id'],
                'action_type' => $data['action_type'],
                'action_value' => $data['action_value'],
                'description' => $data['description'] ?? null,
            ]);
            return $action->load('expertRule');
        },);
    }
    public function update(RuleAction $ruleAction, array $data,): RuleAction
    {
        DB::transaction(function () use ($ruleAction, $data): void {
            $ruleAction->update($data);
        });
        return $ruleAction->refresh()->load('expertRule');
    }
    public function delete(RuleAction $ruleAction): void
    {
        DB::transaction(static function () use ($ruleAction): void {
            $ruleAction->delete();
        });
    }
}
