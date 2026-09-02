<?php

namespace App\Services;

use App\Models\LeaveType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeaveTypeService
{
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
                return LeaveType::query()->create([
                    'name' => $data['name'],
                    'code' => $data['code'],
                    'default_days' => $data['default_days'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'],
                ]);
            }
        );
    }

    public function update(
        LeaveType $leaveType,
        array $data,
    ): LeaveType {
        DB::transaction(
            function () use ($leaveType, $data): void {
                $leaveType->update($data);
            }
        );

        return $leaveType->refresh();
    }

    public function delete(LeaveType $leaveType): void
    {
        DB::transaction(
            static function () use ($leaveType): void {
                $leaveType->delete();
            }
        );
    }
}
