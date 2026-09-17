<?php

namespace App\Services\Competency;

use App\Models\EmployeeCompetency;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeCompetencyService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return EmployeeCompetency::query()
            ->with([
                'employee',
                'competency',
                'competencyLevel',
                'assessor',
            ])
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->whereHas('employee', function ($query) use ($search): void {
                                    $query
                                        ->where('employee_number', 'like', "%{$search}%")
                                        ->orWhere('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('competency', function ($query) use ($search): void {
                                    $query
                                        ->where('code', 'like', "%{$search}%")
                                        ->orWhere('name', 'like', "%{$search}%")
                                        ->orWhere('category', 'like', "%{$search}%");
                                })
                                ->orWhereHas('competencyLevel', function ($query) use ($search): void {
                                    $query->where('name', 'like', "%{$search}%");
                                });
                        }
                    );
                },
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): EmployeeCompetency
    {
        return EmployeeCompetency::query()
            ->with([
                'employee',
                'competency',
                'competencyLevel',
                'assessor',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): EmployeeCompetency
    {
        return DB::transaction(
            function () use ($data): EmployeeCompetency {
                $employeeCompetency = EmployeeCompetency::query()->create([
                    'employee_id' => $data['employee_id'],
                    'competency_id' => $data['competency_id'],
                    'competency_level_id' => $data['competency_level_id'],
                    'score' => $data['score'] ?? null,
                    'assessed_at' => $data['assessed_at'] ?? null,
                    'assessed_by' => $data['assessed_by'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'employee_competency',
                    target: $employeeCompetency,
                    newValues: [
                        'employee_id' => $employeeCompetency->employee_id,
                        'competency_id' => $employeeCompetency->competency_id,
                        'competency_level_id' => $employeeCompetency->competency_level_id,
                        'score' => $employeeCompetency->score,
                        'assessed_at' => $employeeCompetency->assessed_at,
                        'assessed_by' => $employeeCompetency->assessed_by,
                        'notes' => $employeeCompetency->notes,
                    ],
                );

                return $employeeCompetency;
            }
        );
    }

    public function update(
        EmployeeCompetency $employeeCompetency,
        array $data,
    ): EmployeeCompetency {
        $oldValues = [
            'employee_id' => $employeeCompetency->employee_id,
            'competency_id' => $employeeCompetency->competency_id,
            'competency_level_id' => $employeeCompetency->competency_level_id,
            'score' => $employeeCompetency->score,
            'assessed_at' => $employeeCompetency->assessed_at,
            'assessed_by' => $employeeCompetency->assessed_by,
            'notes' => $employeeCompetency->notes,
        ];

        DB::transaction(
            function () use ($employeeCompetency, $data): void {
                $employeeCompetency->update($data);
            }
        );

        $employeeCompetency->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'employee_competency',
            target: $employeeCompetency,
            oldValues: $oldValues,
            newValues: [
                'employee_id' => $employeeCompetency->employee_id,
                'competency_id' => $employeeCompetency->competency_id,
                'competency_level_id' => $employeeCompetency->competency_level_id,
                'score' => $employeeCompetency->score,
                'assessed_at' => $employeeCompetency->assessed_at,
                'assessed_by' => $employeeCompetency->assessed_by,
                'notes' => $employeeCompetency->notes,
            ],
        );

        return $employeeCompetency;
    }

    public function delete(EmployeeCompetency $employeeCompetency): void
    {
        $oldValues = [
            'employee_id' => $employeeCompetency->employee_id,
            'competency_id' => $employeeCompetency->competency_id,
            'competency_level_id' => $employeeCompetency->competency_level_id,
            'score' => $employeeCompetency->score,
            'assessed_at' => $employeeCompetency->assessed_at,
            'assessed_by' => $employeeCompetency->assessed_by,
            'notes' => $employeeCompetency->notes,
        ];

        DB::transaction(
            static function () use ($employeeCompetency): void {
                $employeeCompetency->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'employee_competency',
            target: $employeeCompetency,
            oldValues: $oldValues,
        );
    }
}
