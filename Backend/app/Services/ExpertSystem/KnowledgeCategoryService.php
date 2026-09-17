<?php

namespace App\Services\ExpertSystem;

use App\Models\KnowledgeCategory;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class KnowledgeCategoryService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return KnowledgeCategory::query()
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                },
            )->latest()->paginate($perPage);
    }

    public function findById(int $id): KnowledgeCategory
    {
        return KnowledgeCategory::query()->with('knowledge')->findOrFail($id);
    }

    public function create(array $data): KnowledgeCategory
    {
        return DB::transaction(
            function () use ($data): KnowledgeCategory {
                $knowledgeCategory = KnowledgeCategory::query()->create([
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'knowledge_category',
                    target: $knowledgeCategory,
                    newValues: $knowledgeCategory->toArray(),
                );

                return $knowledgeCategory;
            },
        );
    }

    public function update(KnowledgeCategory $knowledgeCategory, array $data): KnowledgeCategory
    {
        DB::transaction(
            function () use ($knowledgeCategory, $data): void {
                $oldValues = $knowledgeCategory->toArray();

                $knowledgeCategory->update($data);

                $knowledgeCategory->refresh();

                $this->activityLogService->log(
                    action: 'update',
                    module: 'knowledge_category',
                    target: $knowledgeCategory,
                    oldValues: $oldValues,
                    newValues: $knowledgeCategory->toArray(),
                );
            },
        );

        return $knowledgeCategory->refresh();
    }

    public function delete(KnowledgeCategory $knowledgeCategory): void
    {
        DB::transaction(
            function () use ($knowledgeCategory): void {
                $oldValues = $knowledgeCategory->toArray();

                $this->activityLogService->log(
                    action: 'delete',
                    module: 'knowledge_category',
                    target: $knowledgeCategory,
                    oldValues: $oldValues,
                );

                $knowledgeCategory->delete();
            },
        );
    }
}
