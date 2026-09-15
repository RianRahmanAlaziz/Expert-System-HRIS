<?php

namespace App\Services\SystemSupport;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    public function log(
        string $action,
        string $module,
        ?Model $target = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'module' => $module,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?int $userId = null,
        ?string $module = null,
        ?string $action = null,
    ): LengthAwarePaginator {
        $query = ActivityLog::query()
            ->with('user')
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('action', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('target_type', 'like', "%{$search}%");
                });
            })->when(
                $userId,
                fn($query) => $query->where('user_id', $userId),
            )->when(
                $module,
                fn($query) => $query->where('module', $module),
            )->when(
                $action,
                fn($query) => $query->where('action', $action),
            )->latest();

        return $query->paginate($perPage);
    }

    public function findById(int $id): ActivityLog
    {
        return ActivityLog::query()->with('user')->findOrFail($id);
    }
}
