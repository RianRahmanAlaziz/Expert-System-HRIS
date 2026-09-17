<?php

namespace App\Services\ExpertSystem;

use App\Models\Knowledge;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class KnowledgeService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $knowledgeCategoryId = null
    ): LengthAwarePaginator {
        return Knowledge::query()
            ->with('knowledgeCategory')
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                },
            )->when(
                $knowledgeCategoryId !== null,
                function ($query) use ($knowledgeCategoryId): void {
                    $query->where('knowledge_category_id', $knowledgeCategoryId,);
                },
            )->latest()->paginate($perPage);
    }

    public function findById(int $id): Knowledge
    {
        return Knowledge::query()->with(['knowledgeCategory', 'expertRules',])->findOrFail($id);
    }

    public function create(array $data): Knowledge
    {
        return DB::transaction(
            function () use ($data): Knowledge {
                $knowledge = Knowledge::query()->create([
                    'knowledge_category_id' => $data['knowledge_category_id'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'version' => $data['version'] ?? 1,
                    'status' => $data['status'],
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'knowledge',
                    target: $knowledge,
                    newValues: $knowledge->toArray(),
                );

                return $knowledge->load('knowledgeCategory');
            },
        );
    }
    public function update(Knowledge $knowledge, array $data,): Knowledge
    {
        DB::transaction(function () use ($knowledge, $data): void {
            $oldValues = $knowledge->toArray();
            $knowledge->update($data);
            $knowledge->refresh();

            $this->activityLogService->log(
                action: 'update',
                module: 'knowledge',
                target: $knowledge,
                oldValues: $oldValues,
                newValues: $knowledge->toArray(),
            );
        });

        return $knowledge->refresh()->load('knowledgeCategory');
    }
    public function delete(Knowledge $knowledge): void
    {
        DB::transaction(function () use ($knowledge): void {
            $oldValues = $knowledge->toArray();

            $this->activityLogService->log(
                action: 'delete',
                module: 'knowledge',
                target: $knowledge,
                oldValues: $oldValues,
            );

            $knowledge->delete();
        });
    }
}
