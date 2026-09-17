<?php

namespace App\Services\ExpertSystem;

use App\Models\Employee;

final class EmployeeFactProvider
{
    public function build(Employee $employee): array
    {
        return [
            ExpertParameter::PERFORMANCE =>
            $this->performance($employee),

            ExpertParameter::COMPETENCY =>
            $this->competency($employee),

            ExpertParameter::ATTENDANCE =>
            $this->attendance($employee),

            ExpertParameter::EXPERIENCE =>
            $this->experience($employee),

            ExpertParameter::LEADERSHIP =>
            $this->leadership($employee),
        ];
    }

    private function performance(Employee $employee): float
    {
        return round(
            (float) (
                $employee->performanceReviews
                ->sortByDesc('review_date')
                ->first()
                ?->overall_score ?? 0
            ),
            2,
        );
    }

    private function competency(Employee $employee): float
    {
        return round(
            (float) $employee->employeeCompetencies->avg('score'),
            2,
        );
    }

    private function attendance(Employee $employee): float
    {
        $records = $employee->attendance;

        if ($records->isEmpty()) {
            return 0.0;
        }

        $attended = $records->where(
            'working_minutes',
            '>',
            0,
        )->count();

        return round(
            ($attended / $records->count()) * 100,
            2,
        );
    }

    private function experience(Employee $employee): float
    {
        if (!$employee->join_date) {
            return 0.0;
        }

        return round(
            $employee->join_date->diffInMonths(now()) / 12,
            2,
        );
    }

    private function leadership(Employee $employee): float
    {
        return round(
            (float) (
                $employee->employeeCompetencies
                ->first(
                    fn($item) =>
                    $item->competency?->code === 'LEAD'
                )
                ?->score ?? 0
            ),
            2,
        );
    }
}
