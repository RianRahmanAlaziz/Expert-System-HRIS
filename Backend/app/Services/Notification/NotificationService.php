<?php

namespace App\Services\Notification;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use App\Models\User;

class NotificationService
{
    public function paginate(
        User $user,
        int $perPage = 15,
        bool $unreadOnly = false,
    ): LengthAwarePaginator {
        return $user->notifications()
            ->when(
                $unreadOnly,
                fn($query) => $query->whereNull('read_at'),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(
        User $user,
        string $id,
    ): DatabaseNotification {
        return $user->notifications()
            ->findOrFail($id);
    }

    public function markAsRead(
        User $user,
        string $id,
    ): DatabaseNotification {
        $notification = $this->findById(
            user: $user,
            id: $id,
        );

        $notification->markAsRead();

        return $notification->refresh();
    }

    public function markAllAsRead(
        User $user,
    ): void {
        $user->unreadNotifications->markAsRead();
    }

    public function delete(
        User $user,
        string $id,
    ): void {
        $notification = $this->findById(
            user: $user,
            id: $id,
        );

        $notification->delete();
    }
}
