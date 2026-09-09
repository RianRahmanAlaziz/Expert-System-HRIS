<?php

namespace App\Services\ExpertSystem;

use App\Models\Knowledge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class KnowledgeService
{
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
                return $knowledge->load('knowledgeCategory');
            },
        );
    }
    public function update(Knowledge $knowledge, array $data,): Knowledge
    {
        DB::transaction(function () use ($knowledge, $data): void {
            $knowledge->update($data);
        });
        return $knowledge->refresh()->load('knowledgeCategory');
    }
    public function delete(Knowledge $knowledge): void
    {
        DB::transaction(static function () use ($knowledge): void {
            $knowledge->delete();
        });
    }
}
