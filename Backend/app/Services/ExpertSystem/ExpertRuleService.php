<?php

namespace App\Services\ExpertSystem;

use App\Models\ExpertRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ExpertRuleService
{
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
            return $rule->load('knowledge');
        },);
    }
    public function update(ExpertRule $expertRule, array $data,): ExpertRule
    {
        DB::transaction(function () use ($expertRule, $data): void {
            $expertRule->update($data);
        });
        return $expertRule->refresh()->load('knowledge');
    }
    public function delete(ExpertRule $expertRule): void
    {
        DB::transaction(static function () use ($expertRule): void {
            $expertRule->delete();
        });
    }
}
