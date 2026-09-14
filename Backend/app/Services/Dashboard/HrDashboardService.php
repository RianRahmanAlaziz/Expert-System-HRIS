<?php

namespace App\Services\Dashboard;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\LeaveRequest;
use App\Models\PerformanceReview;
use App\Models\PositionRequirement;
use App\Models\Recommendation;

class HrDashboardService
{
    /**
     * Get HR dashboard summary.
     */
    public function getSummary(): array
    {
        return [
            'employee' => $this->getEmployeeStatistics(),
            'attendance' => $this->getAttendanceStatistics(),
            'leave' => $this->getLeaveStatistics(),
            'performance' => $this->getPerformanceStatistics(),
            'competency' => $this->getCompetencyStatistics(),
            'employee_turnover' => $this->getEmployeeTurnover(),
            'expert_system' => $this->getExpertSystemStatistics(),
            'recommendation' => $this->getRecommendationStatistics(),
        ];
    }

    /**
     * Get employee statistics.
     */
    private function getEmployeeStatistics(): array
    {
        return [
            'total' => Employee::query()->count(),

            'active' => Employee::query()
                ->where('employment_status', 'active')
                ->count(),

            'inactive' => Employee::query()
                ->where('employment_status', '!=', 'active')
                ->count(),
        ];
    }

    /**
     * Get today's attendance statistics.
     */
    private function getAttendanceStatistics(): array
    {
        $query = Attendance::query()
            ->whereDate('attendance_date', today());

        return [
            'date' => today()->toDateString(),

            'total' => (clone $query)->count(),

            'present' => (clone $query)
                ->where('status', 'present')
                ->count(),

            'late' => (clone $query)
                ->where('status', 'late')
                ->count(),

            'absent' => (clone $query)
                ->where('status', 'absent')
                ->count(),
        ];
    }

    /**
     * Get pending leave request statistics.
     */
    private function getLeaveStatistics(): array
    {
        return [
            'pending' => LeaveRequest::query()
                ->where('status', 'pending')
                ->count(),
        ];
    }

    /**
     * Get performance statistics.
     */
    private function getPerformanceStatistics(): array
    {
        $averageScore = PerformanceReview::query()
            ->whereNotNull('overall_score')
            ->avg('overall_score');

        return [
            'average_score' => round(
                (float) ($averageScore ?? 0),
                2,
            ),
        ];
    }

    /**
     * Get competency gap statistics.
     *
     * A competency gap exists when an employee does not
     * fulfill a required competency for their current position.
     */
    private function getCompetencyStatistics(): array
    {
        $employees = Employee::query()
            ->where('employment_status', 'active')
            ->with([
                'employeeCompetencies',
                'position',
            ])
            ->get();

        $positionIds = $employees
            ->pluck('position_id')
            ->filter()
            ->unique()
            ->values();

        if ($positionIds->isEmpty()) {
            return [
                'gap_count' => 0,
            ];
        }

        $requirements = PositionRequirement::query()
            ->whereIn('position_id', $positionIds)
            ->where('is_active', true)
            ->with([
                'positionRequirementCompetencies.requiredLevel',
            ])
            ->get()
            ->groupBy('position_id');

        $gapCount = 0;

        foreach ($employees as $employee) {
            $requirement = $requirements
                ->get($employee->position_id)
                ?->sortByDesc('created_at')
                ->first();

            if ($requirement === null) {
                continue;
            }

            foreach (
                $requirement->positionRequirementCompetencies
                as $requiredCompetency
            ) {
                if (!$requiredCompetency->is_required) {
                    continue;
                }

                $employeeCompetency = $employee
                    ->employeeCompetencies
                    ->firstWhere(
                        'competency_id',
                        $requiredCompetency->competency_id,
                    );

                if ($employeeCompetency === null) {
                    $gapCount++;

                    continue;
                }

                $minimumScore = $requiredCompetency->minimum_score;

                if (
                    $minimumScore !== null
                    && (
                        $employeeCompetency->score === null
                        || (float) $employeeCompetency->score
                        < (float) $minimumScore
                    )
                ) {
                    $gapCount++;

                    continue;
                }

                $requiredLevel = $requiredCompetency->requiredLevel;

                if (
                    $requiredLevel !== null
                    && (
                        $employeeCompetency->competencyLevel === null
                        || (int) $employeeCompetency->competencyLevel->level
                        < (int) $requiredLevel->level
                    )
                ) {
                    $gapCount++;
                }
            }
        }

        return [
            'gap_count' => $gapCount,
        ];
    }

    /**
     * Get employee turnover statistics.
     *
     * The current backend does not maintain a reliable termination
     * event through employment history when employment_status changes.
     *
     * Therefore, turnover is not inferred from inactive employees.
     */
    private function getEmployeeTurnover(): array
    {
        return [
            'count' => 0,
            'rate' => 0,
        ];
    }

    /**
     * Get current recommendation statistics.
     */
    private function getRecommendationStatistics(): array
    {
        $currentStatuses = [
            'pending',
            'approved',
            'implemented',
        ];

        return [
            'training' => Recommendation::query()
                ->where('type', 'training')
                ->whereIn('status', $currentStatuses)
                ->count(),

            'promotion' => Recommendation::query()
                ->where('type', 'promotion')
                ->whereIn('status', $currentStatuses)
                ->count(),
        ];
    }

    private function getExpertSystemStatistics(): array
    {
        $consultationQuery = ExpertConsultation::query();
        $recommendationQuery = Recommendation::query();

        return [
            'total_consultations' => (clone $consultationQuery)->count(),

            'recommendation_distribution' => [
                'promotion' => (clone $recommendationQuery)
                    ->where('type', 'promotion')
                    ->count(),

                'training' => (clone $recommendationQuery)
                    ->where('type', 'training')
                    ->count(),

                'career' => (clone $recommendationQuery)
                    ->where('type', 'career')
                    ->count(),

                'performance_improvement' => (clone $recommendationQuery)
                    ->where('type', 'performance_improvement')
                    ->count(),

                'employee_risk' => (clone $recommendationQuery)
                    ->where('type', 'employee_risk')
                    ->count(),
            ],

            'high_risk_employee' => (clone $recommendationQuery)
                ->where('type', 'employee_risk')
                ->whereIn('status', ['pending', 'approved'])
                ->count(),

            'promotion_candidate' => (clone $recommendationQuery)
                ->where('type', 'promotion')
                ->whereIn('status', ['pending', 'approved'])
                ->count(),

            'training_candidate' => (clone $recommendationQuery)
                ->where('type', 'training')
                ->whereIn('status', ['pending', 'approved'])
                ->count(),

            'competency_gap' => $this->getCompetencyStatistics()['gap_count'],

            'recent_consultations' => (clone $consultationQuery)
                ->with([
                    'employee:id,employee_number,first_name,last_name',
                    'result:id,expert_consultation_id,recommendation,score,confidence',
                ])
                ->latest('started_at')
                ->limit(5)
                ->get(),
        ];
    }
}
