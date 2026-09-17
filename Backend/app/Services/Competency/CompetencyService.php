<?php

namespace App\Services\Competency;

use App\Models\Competency;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CompetencyService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return Competency::query()
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('category', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        }
                    );
                },
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): Competency
    {
        return Competency::query()->findOrFail($id);
    }

    public function create(array $data): Competency
    {
        return DB::transaction(
            function () use ($data): Competency {
                $competency = Competency::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'competency',
                    target: $competency,
                    newValues: [
                        'code' => $competency->code,
                        'name' => $competency->name,
                        'category' => $competency->category,
                        'description' => $competency->description,
                        'status' => $competency->status,
                    ],
                );

                return $competency;
            }
        );
    }

    public function update(
        Competency $competency,
        array $data,
    ): Competency {
        $oldValues = [
            'code' => $competency->code,
            'name' => $competency->name,
            'category' => $competency->category,
            'description' => $competency->description,
            'status' => $competency->status,
        ];

        DB::transaction(
            function () use ($competency, $data): void {
                $competency->update($data);
            }
        );

        $competency->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'competency',
            target: $competency,
            oldValues: $oldValues,
            newValues: [
                'code' => $competency->code,
                'name' => $competency->name,
                'category' => $competency->category,
                'description' => $competency->description,
                'status' => $competency->status,
            ],
        );

        return $competency;
    }

    public function delete(Competency $competency): void
    {
        $oldValues = [
            'code' => $competency->code,
            'name' => $competency->name,
            'category' => $competency->category,
            'description' => $competency->description,
            'status' => $competency->status,
        ];

        DB::transaction(
            static function () use ($competency): void {
                $competency->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'competency',
            target: $competency,
            oldValues: $oldValues,
        );
    }
}
