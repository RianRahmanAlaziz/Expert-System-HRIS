<?php

namespace App\Services\Leave;

use App\Models\LeaveType;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeaveTypeService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator {
        return LeaveType::query()
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        }
                    );
                },
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): LeaveType
    {
        return LeaveType::query()->findOrFail($id);
    }

    public function create(array $data): LeaveType
    {
        return DB::transaction(
            function () use ($data): LeaveType {
                $leaveType = LeaveType::query()->create([
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'default_days' => $data['default_days'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'leave_type',
                    target: $leaveType,
                    newValues: [
                        'name' => $leaveType->name,
                        'code' => $leaveType->code,
                        'default_days' => $leaveType->default_days,
                        'description' => $leaveType->description,
                        'status' => $leaveType->status,
                    ],
                );

                return $leaveType;
            }
        );
    }

    public function update(
        LeaveType $leaveType,
        array $data,
    ): LeaveType {
        $oldValues = [
            'name' => $leaveType->name,
            'code' => $leaveType->code,
            'default_days' => $leaveType->default_days,
            'description' => $leaveType->description,
            'status' => $leaveType->status,
        ];

        DB::transaction(
            function () use ($leaveType, $data): void {
                $leaveType->update($data);
            }
        );

        $leaveType->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'leave_type',
            target: $leaveType,
            oldValues: $oldValues,
            newValues: [
                'name' => $leaveType->name,
                'code' => $leaveType->code,
                'default_days' => $leaveType->default_days,
                'description' => $leaveType->description,
                'status' => $leaveType->status,
            ],
        );

        return $leaveType;
    }

    public function delete(LeaveType $leaveType): void
    {
        $oldValues = [
            'name' => $leaveType->name,
            'code' => $leaveType->code,
            'default_days' => $leaveType->default_days,
            'description' => $leaveType->description,
            'status' => $leaveType->status,
        ];

        DB::transaction(
            static function () use ($leaveType): void {
                $leaveType->delete();
            }
        );

        $this->activityLogService->log(
            action: 'delete',
            module: 'leave_type',
            target: $leaveType,
            oldValues: $oldValues,
        );
    }
}
