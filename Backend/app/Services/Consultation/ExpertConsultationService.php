<?php

namespace App\Services\Consultation;

use App\Models\ConsultationResult;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\ExpertRule;
use App\Models\User;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExpertConsultationService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        ?string $search = null,
        ?int $employeeId = null,
        ?string $consultationType = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return ExpertConsultation::query()
            ->with([
                'employee',
                'user',
                'result',
            ])
            ->when(
                filled($search),
                function (Builder $query) use ($search): void {
                    $search = trim($search);

                    $query->whereHas('employee', function (Builder $employeeQuery) use ($search): void {
                        $employeeQuery
                            ->where('employee_number', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                $employeeId,
                fn(Builder $query) => $query->where('employee_id', $employeeId),
            )
            ->when(
                filled($consultationType),
                fn(Builder $query) => $query->where(
                    'consultation_type',
                    trim($consultationType),
                ),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ExpertConsultation
    {
        return ExpertConsultation::query()
            ->with([
                'employee',
                'user',
                'result',
            ])
            ->findOrFail($id);
    }

    public function create(
        User $user,
        array $data,
    ): ExpertConsultation {
        return DB::transaction(function () use ($user, $data): ExpertConsultation {
            $employee = Employee::query()
                ->with([
                    'department',
                    'position',
                    'employmentHistories',
                    'attendance',
                    'performanceReviews',
                    'employeeCompetencies',
                ])
                ->findOrFail($data['employee_id']);

            $consultation = ExpertConsultation::query()->create([
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'consultation_type' => $data['consultation_type'],
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $inputSnapshot = $this->buildInputSnapshot($employee);

            $facts = $this->buildFacts($employee);

            $rules = ExpertRule::query()
                ->with([
                    'conditions',
                    'actions',
                ])
                ->where('status', 'active')
                ->whereHas('knowledge', function (Builder $query): void {
                    $query->where('status', 'active');
                })
                ->orderBy('priority')
                ->get();

            $evaluation = $this->evaluateRules(
                rules: $rules,
                facts: $facts,
            );

            $score = $this->calculateWeightedScore(
                facts: $facts,
            );

            $recommendation = $this->determineRecommendation(
                evaluation: $evaluation,
            );

            $confidence = $this->calculateConfidence(
                evaluation: $evaluation,
            );

            $reason = $this->buildReason(
                facts: $facts,
                evaluation: $evaluation,
            );

            ConsultationResult::query()->create([
                'expert_consultation_id' => $consultation->id,
                'recommendation' => $recommendation,
                'score' => $score,
                'confidence' => $confidence,
                'reason' => $reason,
                'input_snapshot' => $inputSnapshot,
                'matched_rules' => $evaluation['matched_rules'],
                'suggested_actions' => $evaluation['suggested_actions'],
            ]);

            $consultation->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->activityLogService->log(
                action: 'create',
                module: 'expert_consultation',
                target: $consultation,
                newValues: [
                    'employee_id' => $consultation->employee_id,
                    'user_id' => $consultation->user_id,
                    'consultation_type' => $consultation->consultation_type,
                    'status' => $consultation->status,
                    'recommendation' => $recommendation,
                    'score' => $score,
                    'confidence' => $confidence,
                ],
            );

            return $consultation->load([
                'employee',
                'user',
                'result',
            ]);
        });
    }

    private function buildInputSnapshot(Employee $employee): array
    {
        return [
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'gender' => $employee->gender,
                'birth_date' => $employee->birth_date?->toDateString(),
                'join_date' => $employee->join_date?->toDateString(),
                'employment_type' => $employee->employment_type,
                'employment_status' => $employee->employment_status,
            ],
            'department' => $employee->department ? [
                'id' => $employee->department->id,
                'name' => $employee->department->name,
            ] : null,
            'position' => $employee->position ? [
                'id' => $employee->position->id,
                'name' => $employee->position->name,
            ] : null,
            'performance_reviews' => $employee->performanceReviews
                ->map(fn($review): array => [
                    'id' => $review->id,
                    'performance_period_id' => $review->performance_period_id,
                    'status' => $review->status,
                    'overall_score' => $review->overall_score,
                    'review_date' => $review->review_date?->toDateString(),
                ])
                ->values()
                ->all(),
            'employee_competencies' => $employee->employeeCompetencies
                ->map(fn($competency): array => [
                    'id' => $competency->id,
                    'competency_id' => $competency->competency_id,
                    'competency_level_id' => $competency->competency_level_id,
                    'score' => $competency->score,
                    'assessed_at' => $competency->assessed_at?->toDateString(),
                ])
                ->values()
                ->all(),
            'attendance' => $employee->attendance
                ->map(fn($attendance): array => [
                    'id' => $attendance->id,
                    'attendance_date' => $attendance->attendance_date?->toDateString(),
                    'status' => $attendance->status,
                    'late_minutes' => $attendance->late_minutes,
                    'working_minutes' => $attendance->working_minutes,
                ])
                ->values()
                ->all(),
            'employment_histories' => $employee->employmentHistories
                ->map(fn($history): array => [
                    'id' => $history->id,
                    'position_id' => $history->position_id,
                    'department_id' => $history->department_id,
                    'employment_type' => $history->employment_type,
                    'start_date' => $history->start_date?->toDateString(),
                    'end_date' => $history->end_date?->toDateString(),
                    'reason' => $history->reason,
                ])
                ->values()
                ->all(),
        ];
    }

    private function buildFacts(Employee $employee): array
    {
        return [
            'performance' => $this->calculatePerformance($employee),
            'competency' => $this->calculateCompetency($employee),
            'attendance' => $this->calculateAttendance($employee),
            'experience' => $this->calculateExperience($employee),
        ];
    }

    private function calculatePerformance(Employee $employee): float
    {
        $review = $employee->performanceReviews
            ->sortByDesc('review_date')
            ->first();

        return round(
            (float) ($review?->overall_score ?? 0),
            2,
        );
    }

    private function calculateCompetency(Employee $employee): float
    {
        if ($employee->employeeCompetencies->isEmpty()) {
            return 0.0;
        }

        return round(
            (float) $employee->employeeCompetencies->avg('score'),
            2,
        );
    }

    private function calculateAttendance(Employee $employee): float
    {
        $attendance = $employee->attendance;

        if ($attendance->isEmpty()) {
            return 0.0;
        }

        $attended = $attendance
            ->filter(
                fn($record): bool => $record->working_minutes !== null
                    && (int) $record->working_minutes > 0,
            )
            ->count();

        return round(
            ($attended / $attendance->count()) * 100,
            2,
        );
    }

    private function calculateExperience(Employee $employee): float
    {
        if (!$employee->join_date) {
            return 0.0;
        }

        $months = $employee->join_date->diffInMonths(now());

        return round(
            $months / 12,
            2,
        );
    }

    private function evaluateRules(
        $rules,
        array $facts,
    ): array {
        $matchedRules = [];
        $suggestedActions = [];

        $totalConditions = 0;
        $passedConditions = 0;

        foreach ($rules as $rule) {
            $conditions = $rule->conditions
                ->sortBy('sort_order')
                ->values();

            if ($conditions->isEmpty()) {
                continue;
            }

            $conditionResults = [];
            $ruleMatched = null;

            foreach ($conditions as $condition) {
                $parameter = $condition->parameter;
                $factExists = array_key_exists($parameter, $facts);

                $passed = $factExists
                    && $this->evaluateCondition(
                        actual: $facts[$parameter],
                        operator: $condition->operator,
                        expected: $condition->value,
                    );

                $conditionResults[] = [
                    'parameter' => $parameter,
                    'operator' => $condition->operator,
                    'value' => $condition->value,
                    'logical_operator' => $condition->logical_operator,
                    'passed' => $passed,
                ];

                $totalConditions++;

                if ($passed) {
                    $passedConditions++;
                }

                if ($ruleMatched === null) {
                    $ruleMatched = $passed;
                    continue;
                }

                $logicalOperator = strtoupper(
                    (string) ($condition->logical_operator ?? 'AND'),
                );

                $ruleMatched = match ($logicalOperator) {
                    'OR' => $ruleMatched || $passed,
                    default => $ruleMatched && $passed,
                };
            }

            if ($ruleMatched !== true) {
                continue;
            }

            $matchedRules[] = [
                'id' => $rule->id,
                'code' => $rule->code,
                'name' => $rule->name,
                'priority' => $rule->priority,
                'conditions' => $conditionResults,
            ];

            foreach ($rule->actions as $action) {
                $suggestedActions[] = [
                    'action_type' => $action->action_type,
                    'action_value' => $action->action_value,
                    'description' => $action->description,
                ];
            }
        }

        return [
            'matched_rules' => $matchedRules,
            'suggested_actions' => $suggestedActions,
            'total_conditions' => $totalConditions,
            'passed_conditions' => $passedConditions,
        ];
    }

    private function evaluateCondition(
        mixed $actual,
        string $operator,
        mixed $expected,
    ): bool {
        if (!is_numeric($actual) || !is_numeric($expected)) {
            $actual = (string) $actual;
            $expected = (string) $expected;

            return match ($operator) {
                '=' => $actual === $expected,
                default => false,
            };
        }

        $actual = (float) $actual;
        $expected = (float) $expected;

        return match ($operator) {
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            '=' => $actual === $expected,
            default => false,
        };
    }

    private function calculateWeightedScore(array $facts): float
    {
        /*
         * Performance = 40%
         * Competency  = 30%
         * Attendance  = 10%
         * Experience  = 20%
         *
         * Experience is converted to a 0-100 scale for scoring.
         * This normalization is an implementation convention, not
         * a formally defined Certainty Factor formula.
         */
        $experienceScore = min(
            $facts['experience'] * 20,
            100,
        );

        return round(
            ($facts['performance'] * 0.40)
                + ($facts['competency'] * 0.30)
                + ($facts['attendance'] * 0.10)
                + ($experienceScore * 0.20),
            2,
        );
    }

    private function calculateConfidence(array $evaluation): float
    {
        $totalConditions = $evaluation['total_conditions'];

        if ($totalConditions === 0) {
            return 0.0;
        }

        return round(
            (
                $evaluation['passed_conditions']
                / $totalConditions
            ) * 100,
            2,
        );
    }

    private function determineRecommendation(
        array $evaluation,
    ): string {
        foreach ($evaluation['suggested_actions'] as $action) {
            if (
                strtolower((string) $action['action_type'])
                === 'recommendation'
            ) {
                return (string) $action['action_value'];
            }
        }

        return 'Not Recommended';
    }

    private function buildReason(
        array $facts,
        array $evaluation,
    ): string {
        $reason = sprintf(
            'Performance %.2f, Competency %.2f, Attendance %.2f, Experience %.2f tahun.',
            $facts['performance'],
            $facts['competency'],
            $facts['attendance'],
            $facts['experience'],
        );

        if (empty($evaluation['matched_rules'])) {
            return $reason . ' Tidak ada rule aktif yang terpenuhi.';
        }

        $ruleNames = collect($evaluation['matched_rules'])
            ->pluck('name')
            ->filter()
            ->values()
            ->implode(', ');

        return $reason
            . ' Rule yang terpenuhi: '
            . $ruleNames
            . '.';
    }
}
