<?php

namespace App\Services\Recommendation;

use App\Models\ExpertConsultation;
use App\Models\Recommendation;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $employeeId = null,
        ?string $type = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        return Recommendation::query()
            ->with([
                'employee',
                'expertConsultation',
            ])
            ->when(
                $search,
                function ($query, string $search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('employee', function ($query) use ($search): void {
                                $query
                                    ->where('employee_number', 'like', "%{$search}%")
                                    ->orWhere('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    });
                },
            )
            ->when(
                $employeeId,
                fn($query, int $employeeId) => $query->where(
                    'employee_id',
                    $employeeId,
                ),
            )
            ->when(
                $type,
                fn($query, string $type) => $query->where('type', $type),
            )
            ->when(
                $status,
                fn($query, string $status) => $query->where('status', $status),
            )
            ->latest('recommended_at')
            ->latest('id')
            ->paginate($perPage);
    }

    public function findById(int $id): Recommendation
    {
        return Recommendation::query()
            ->with([
                'employee',
                'expertConsultation',
                'histories.user',
            ])
            ->findOrFail($id);
    }

    public function createFromConsultation(
        ExpertConsultation $consultation,
        array $data,
    ): Recommendation {
        return DB::transaction(function () use ($consultation, $data): Recommendation {
            $consultation->loadMissing([
                'employee',
                'result',
            ]);

            if (!$consultation->result) {
                throw new ModelNotFoundException(
                    'Consultation result tidak ditemukan.',
                );
            }

            $result = $consultation->result;

            $recommendation = Recommendation::query()->create([
                'employee_id' => $consultation->employee_id,
                'expert_consultation_id' => $consultation->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'description' => $data['description']
                    ?? $result->reason,
                'priority' => $data['priority'] ?? 'medium',
                'status' => 'pending',
                'recommended_at' => now(),
            ]);

            $this->activityLogService->log(
                action: 'create',
                module: 'recommendation',
                target: $recommendation,
                newValues: [
                    'employee_id' => $recommendation->employee_id,
                    'expert_consultation_id' => $recommendation->expert_consultation_id,
                    'type' => $recommendation->type,
                    'title' => $recommendation->title,
                    'priority' => $recommendation->priority,
                    'status' => $recommendation->status,
                ],
                userId: $consultation->user_id,
            );

            return $recommendation;
        });
    }

    public function updateStatus(
        Recommendation $recommendation,
        string $status,
        ?string $notes = null,
        ?int $userId = null,
    ): Recommendation {
        return DB::transaction(function () use (
            $recommendation,
            $status,
            $notes,
            $userId,
        ): Recommendation {
            $oldStatus = $recommendation->status;

            if ($oldStatus === $status) {
                return $recommendation->load([
                    'employee',
                    'expertConsultation',
                    'histories.user',
                ]);
            }

            $recommendation->update([
                'status' => $status,
            ]);

            if ($userId !== null) {
                $recommendation->histories()->create([
                    'user_id' => $userId,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'notes' => $notes,
                ]);
            }

            $this->activityLogService->log(
                action: 'update_status',
                module: 'recommendation',
                target: $recommendation,
                oldValues: [
                    'status' => $oldStatus,
                ],
                newValues: [
                    'status' => $status,
                    'notes' => $notes,
                ],
                userId: $userId,
            );

            return $recommendation->fresh([
                'employee',
                'expertConsultation',
                'histories.user',
            ]);
        });
    }
}
