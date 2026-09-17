<?php

namespace App\Services\ExpertSystem;

use App\Models\RuleAction;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RuleActionService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

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

            $this->activityLogService->log(
                action: 'create',
                module: 'rule_action',
                target: $action,
                newValues: $action->toArray(),
            );

            return $action->load('expertRule');
        });
    }

    public function update(RuleAction $ruleAction, array $data,): RuleAction
    {
        DB::transaction(function () use ($ruleAction, $data): void {
            $oldValues = $ruleAction->toArray();

            $ruleAction->update($data);

            $ruleAction->refresh();

            $this->activityLogService->log(
                action: 'update',
                module: 'rule_action',
                target: $ruleAction,
                oldValues: $oldValues,
                newValues: $ruleAction->toArray(),
            );
        });

        return $ruleAction->refresh()->load('expertRule');
    }

    public function delete(RuleAction $ruleAction): void
    {
        DB::transaction(function () use ($ruleAction): void {
            $oldValues = $ruleAction->toArray();

            $this->activityLogService->log(
                action: 'delete',
                module: 'rule_action',
                target: $ruleAction,
                oldValues: $oldValues,
            );

            $ruleAction->delete();
        });
    }
}
