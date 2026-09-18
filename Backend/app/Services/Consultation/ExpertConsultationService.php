<?php

namespace App\Services\Consultation;

use App\Models\ConsultationResult;
use App\Models\Employee;
use App\Models\ExpertConsultation;
use App\Models\ExpertRule;
use App\Models\User;
use App\Services\ExpertSystem\EmployeeFactProvider;
use App\Services\ExpertSystem\InferenceEngine;
use App\Services\ExpertSystem\WeightedScoreCalculator;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ExpertConsultationService
{
    public function __construct(
        private readonly EmployeeFactProvider $factProvider,
        private readonly InferenceEngine $inferenceEngine,
        private readonly WeightedScoreCalculator $scoreCalculator,
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
        return DB::transaction(function () use (
            $user,
            $data,
        ): ExpertConsultation {
            $employee = Employee::query()
                ->with([
                    'department',
                    'position',
                    'employmentHistories',
                    'attendance',
                    'performanceReviews',
                    'employeeCompetencies.competency',
                ])
                ->findOrFail($data['employee_id']);

            $consultation = ExpertConsultation::query()->create([
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'consultation_type' => $data['consultation_type'],
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $facts = $this->factProvider->build($employee);

            $rules = ExpertRule::query()
                ->with([
                    'conditions',
                    'actions',
                ])
                ->where('status', 'active')
                ->whereHas(
                    'knowledge',
                    fn($query) =>
                    $query->where('status', 'active')
                )
                ->orderBy('priority')
                ->get();

            $evaluation = $this->inferenceEngine->evaluate(
                rules: $rules,
                facts: $facts,
            );

            $score = $this->scoreCalculator->calculate(
                facts: $facts,
            );

            $recommendation = $this->resolveRecommendation($evaluation);

            $confidence = $this->calculateConfidence($evaluation);

            $result = ConsultationResult::query()->create([
                'expert_consultation_id' => $consultation->id,
                'recommendation' => $recommendation,
                'score' => $score,
                'confidence' => $confidence,
                'reason' => $this->buildReason(
                    $facts,
                    $evaluation,
                ),
                'input_snapshot' => [
                    'employee' => [
                        'id' => $employee->id,
                    ],
                    'facts' => $facts,
                ],
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
                    'recommendation' => $result->recommendation,
                    'score' => $result->score,
                    'confidence' => $result->confidence,
                ],
                userId: $user->id,
            );

            return $consultation->load([
                'employee',
                'user',
                'result',
            ]);
        });
    }

    private function resolveRecommendation(
        array $evaluation,
    ): string {
        foreach (
            $evaluation['suggested_actions']
            as $action
        ) {
            if (
                strtolower(
                    (string) $action['action_type']
                ) === 'recommendation'
            ) {
                return (string) $action['action_value'];
            }
        }

        return 'Not Recommended';
    }

    private function calculateConfidence(
        array $evaluation,
    ): float {
        if ($evaluation['total_conditions'] === 0) {
            return 0;
        }

        return round(
            (
                $evaluation['passed_conditions']
                / $evaluation['total_conditions']
            ) * 100,
            2,
        );
    }

    private function buildReason(
        array $facts,
        array $evaluation,
    ): string {
        $rules = collect(
            $evaluation['matched_rules']
        )
            ->pluck('name')
            ->filter()
            ->implode(', ');

        return $rules === ''
            ? 'Tidak ada rule aktif yang terpenuhi.'
            : "Rule yang terpenuhi: {$rules}.";
    }
}
