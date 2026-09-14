<?php

namespace App\Services\Competency;

use App\Models\Employee;
use App\Models\PositionRequirement;
use Illuminate\Support\Collection;

class CompetencyReportService
{
    /**
     * Generate competency report.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generate(array $filters = []): array
    {
        $employees = Employee::query()
            ->where('employment_status', 'active')
            ->with([
                'department',
                'position',
                'employeeCompetencies.competency',
                'employeeCompetencies.competencyLevel',
            ])
            ->when(
                !empty($filters['employee_id']),
                function ($query) use ($filters): void {
                    $query->where(
                        'id',
                        $filters['employee_id'],
                    );
                },
            )
            ->when(
                !empty($filters['department_id']),
                function ($query) use ($filters): void {
                    $query->where(
                        'department_id',
                        $filters['department_id'],
                    );
                },
            )
            ->when(
                !empty($filters['position_id']),
                function ($query) use ($filters): void {
                    $query->where(
                        'position_id',
                        $filters['position_id'],
                    );
                },
            )
            ->get();

        if ($employees->isEmpty()) {
            return [];
        }

        $positionIds = $employees
            ->pluck('position_id')
            ->filter()
            ->unique()
            ->values();

        $requirements = PositionRequirement::query()
            ->whereIn('position_id', $positionIds)
            ->where('is_active', true)
            ->with([
                'positionRequirementCompetencies.competency',
                'positionRequirementCompetencies.requiredLevel',
            ])
            ->get()
            ->groupBy('position_id')
            ->map(
                fn(Collection $positionRequirements) =>
                $positionRequirements
                    ->sortByDesc('created_at')
                    ->first(),
            );

        $report = [];

        foreach ($employees as $employee) {
            $requirement = $requirements->get($employee->position_id);

            if ($requirement === null) {
                continue;
            }

            foreach (
                $requirement->positionRequirementCompetencies
                as $requiredCompetency
            ) {
                $employeeCompetency = $employee
                    ->employeeCompetencies
                    ->firstWhere(
                        'competency_id',
                        $requiredCompetency->competency_id,
                    );

                $requiredLevel = $requiredCompetency->requiredLevel;
                $currentLevel = $employeeCompetency?->competencyLevel;

                $minimumScore = $requiredCompetency->minimum_score;
                $currentScore = $employeeCompetency?->score;

                $scoreGap = (
                    $minimumScore !== null
                    && (
                        $currentScore === null
                        || (float) $currentScore < (float) $minimumScore
                    )
                );

                $levelGap = (
                    $requiredLevel !== null
                    && (
                        $currentLevel === null
                        || (int) $currentLevel->level
                        < (int) $requiredLevel->level
                    )
                );

                $hasGap = $requiredCompetency->is_required
                    && (
                        $employeeCompetency === null
                        || $scoreGap
                        || $levelGap
                    );

                $report[] = [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'employee_name' => trim(
                        $employee->first_name
                            . ' '
                            . $employee->last_name,
                    ),

                    'department_id' => $employee->department?->id,
                    'department_name' => $employee->department?->name,

                    'position_id' => $employee->position?->id,
                    'position_name' => $employee->position?->name,

                    'competency_id' => $requiredCompetency->competency_id,
                    'competency_code' =>
                    $requiredCompetency->competency?->code,
                    'competency_name' =>
                    $requiredCompetency->competency?->name,
                    'category' =>
                    $requiredCompetency->competency?->category,

                    'current_level' => $currentLevel?->level,
                    'current_level_name' => $currentLevel?->name,
                    'current_score' => $currentScore,

                    'required_level' => $requiredLevel?->level,
                    'required_level_name' => $requiredLevel?->name,
                    'minimum_score' => $minimumScore,

                    'is_required' => (bool) $requiredCompetency->is_required,
                    'has_gap' => $hasGap,
                ];
            }
        }

        if (!empty($filters['competency_id'])) {
            $report = array_values(
                array_filter(
                    $report,
                    fn(array $item): bool =>
                    $item['competency_id']
                        === (int) $filters['competency_id'],
                ),
            );
        }

        return $report;
    }
}
