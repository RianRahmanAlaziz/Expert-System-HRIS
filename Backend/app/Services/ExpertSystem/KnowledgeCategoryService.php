<?php

namespace App\Services\ExpertSystem;

use App\Models\KnowledgeCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class KnowledgeCategoryService
{
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
                return KnowledgeCategory::query()->create([
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                ]);
            },
        );
    }

    public function update(KnowledgeCategory $knowledgeCategory, array $data): KnowledgeCategory
    {
        DB::transaction(
            function () use ($knowledgeCategory, $data): void {
                $knowledgeCategory->update($data);
            }
        );
        return $knowledgeCategory->refresh();
    }

    public function delete(KnowledgeCategory $knowledgeCategory): void
    {
        DB::transaction(
            static function () use ($knowledgeCategory): void {
                $knowledgeCategory->delete();
            }
        );
    }
}
