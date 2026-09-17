<?php

namespace App\Services\Training;

use App\Models\TrainingParticipant;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TrainingParticipantService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?int $trainingId = null,
        ?int $employeeId = null,
    ): LengthAwarePaginator {
        return TrainingParticipant::query()
            ->when(
                $trainingId !== null,
                fn($query) => $query->where(
                    'training_id',
                    $trainingId
                ),
            )
            ->when(
                $employeeId !== null,
                fn($query) => $query->where(
                    'employee_id',
                    $employeeId
                ),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): TrainingParticipant
    {
        return TrainingParticipant::query()->findOrFail($id);
    }

    public function create(array $data): TrainingParticipant
    {
        return DB::transaction(
            function () use ($data): TrainingParticipant {
                $participant = TrainingParticipant::query()->create([
                    'training_id' => $data['training_id'],
                    'employee_id' => $data['employee_id'],
                    'status' => $data['status'] ?? 'registered',
                    'registered_at' => $data['registered_at'] ?? now(),
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'training_participant',
                    target: $participant,
                    newValues: [
                        'training_id' => $participant->training_id,
                        'employee_id' => $participant->employee_id,
                        'status' => $participant->status,
                        'score' => $participant->score,
                        'registered_at' => $participant->registered_at?->toDateTimeString(),
                        'completed_at' => $participant->completed_at?->toDateTimeString(),
                        'certificate_path' => $participant->certificate_path,
                    ],
                );

                return $participant;
            }
        );
    }

    public function update(
        TrainingParticipant $participant,
        array $data,
    ): TrainingParticipant {
        $oldValues = [
            'training_id' => $participant->training_id,
            'employee_id' => $participant->employee_id,
            'status' => $participant->status,
            'score' => $participant->score,
            'registered_at' => $participant->registered_at?->toDateTimeString(),
            'completed_at' => $participant->completed_at?->toDateTimeString(),
            'certificate_path' => $participant->certificate_path,
        ];

        DB::transaction(
            function () use ($participant, $data): void {
                $participant->update($data);
            }
        );

        $participant->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'training_participant',
            target: $participant,
            oldValues: $oldValues,
            newValues: [
                'training_id' => $participant->training_id,
                'employee_id' => $participant->employee_id,
                'status' => $participant->status,
                'score' => $participant->score,
                'registered_at' => $participant->registered_at?->toDateTimeString(),
                'completed_at' => $participant->completed_at?->toDateTimeString(),
                'certificate_path' => $participant->certificate_path,
            ],
        );

        return $participant;
    }

    public function evaluate(
        TrainingParticipant $participant,
        float $score,
    ): TrainingParticipant {
        $oldScore = $participant->score;

        DB::transaction(
            function () use ($participant, $score): void {
                $participant->update([
                    'score' => $score,
                ]);
            }
        );

        $participant->refresh();

        $this->activityLogService->log(
            action: 'evaluate',
            module: 'training_participant',
            target: $participant,
            oldValues: [
                'score' => $oldScore,
            ],
            newValues: [
                'score' => $participant->score,
            ],
        );

        return $participant;
    }

    public function history(
        int $employeeId,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return TrainingParticipant::query()
            ->with('training')
            ->where('employee_id', $employeeId)
            ->latest('registered_at')
            ->paginate($perPage);
    }

    public function delete(TrainingParticipant $participant): void
    {
        $oldValues = [
            'training_id' => $participant->training_id,
            'employee_id' => $participant->employee_id,
            'status' => $participant->status,
            'score' => $participant->score,
            'registered_at' => $participant->registered_at?->toDateTimeString(),
            'completed_at' => $participant->completed_at?->toDateTimeString(),
            'certificate_path' => $participant->certificate_path,
        ];

        DB::transaction(
            function () use ($participant): void {
                $participant->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'training_participant',
            target: $participant,
            oldValues: $oldValues,
        );
    }
}
