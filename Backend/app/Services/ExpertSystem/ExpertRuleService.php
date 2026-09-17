<?php

namespace App\Services\ExpertSystem;

use App\Models\ExpertRule;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ExpertRuleService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $knowledgeId = null
    ): LengthAwarePaginator {
        return ExpertRule::query()
            ->with('knowledge')
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                },
            )->when(
                $knowledgeId !== null,
                function ($query) use ($knowledgeId): void {
                    $query->where('knowledge_id', $knowledgeId);
                },
            )->orderBy('priority')->latest()->paginate($perPage);
    }
    public function findById(int $id): ExpertRule
    {
        return ExpertRule::query()->with([
            'knowledge',
            'conditions',
            'actions',
        ])->findOrFail($id);
    }

    public function create(array $data): ExpertRule
    {
        return DB::transaction(function () use ($data): ExpertRule {
            $rule = ExpertRule::query()->create([
                'knowledge_id' => $data['knowledge_id'],
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? 1,
                'status' => $data['status'],
            ]);

            $this->activityLogService->log(
                action: 'create',
                module: 'expert_rule',
                target: $rule,
                newValues: $rule->toArray(),
            );

            return $rule->load('knowledge');
        });
    }

    public function update(ExpertRule $expertRule, array $data,): ExpertRule
    {
        DB::transaction(function () use ($expertRule, $data): void {
            $oldValues = $expertRule->toArray();

            $expertRule->update($data);

            $expertRule->refresh();

            $this->activityLogService->log(
                action: 'update',
                module: 'expert_rule',
                target: $expertRule,
                oldValues: $oldValues,
                newValues: $expertRule->toArray(),
            );
        });

        return $expertRule->refresh()->load('knowledge');
    }

    public function delete(ExpertRule $expertRule): void
    {
        DB::transaction(function () use ($expertRule): void {
            $oldValues = $expertRule->toArray();

            $this->activityLogService->log(
                action: 'delete',
                module: 'expert_rule',
                target: $expertRule,
                oldValues: $oldValues,
            );

            $expertRule->delete();
        });
    }
}
