<?php

namespace App\Services\Training;

use App\Models\TrainingParticipant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TrainingParticipantService
{
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
                return TrainingParticipant::query()->create([
                    'training_id' => $data['training_id'],
                    'employee_id' => $data['employee_id'],
                    'status' => $data['status'] ?? 'registered',
                    'registered_at' => $data['registered_at'] ?? now(),
                ]);
            }
        );
    }

    public function update(
        TrainingParticipant $participant,
        array $data,
    ): TrainingParticipant {
        DB::transaction(
            function () use ($participant, $data): void {
                $participant->update($data);
            }
        );

        return $participant->refresh();
    }

    public function evaluate(
        TrainingParticipant $participant,
        float $score,
    ): TrainingParticipant {
        DB::transaction(
            function () use ($participant, $score): void {
                $participant->update([
                    'score' => $score,
                ]);
            }
        );

        return $participant->refresh();
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
        DB::transaction(
            static function () use ($participant): void {
                $participant->delete();
            }
        );
    }
}
