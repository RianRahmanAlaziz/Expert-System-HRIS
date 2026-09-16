<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\IndexNotificationRequest;
use App\Http\Resources\V1\Notification\NotificationResource;
use App\Services\Notification\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NotificationController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:notification.view', only: ['index', 'show']),
            new Middleware('permission:notification.read', only: ['markAsRead', 'markAllAsRead']),
            new Middleware('permission:notification.delete', only: ['destroy']),
        ];
    }

    public function index(IndexNotificationRequest $request): JsonResponse
    {
        $notifications = $this->notificationService->paginate(
            user: $request->user(),
            perPage: $request->integer('per_page', 15),
            unreadOnly: $request->boolean('unread_only'),
        );

        return ApiResponse::success(
            data: NotificationResource::collection($notifications),
            message: 'Data notifikasi berhasil diambil.',
        );
    }

    public function show(Request $request, string $notification): JsonResponse
    {
        $result = $this->notificationService->findById(
            user: $request->user(),
            id: $notification,
        );

        return ApiResponse::success(
            data: new NotificationResource($result),
            message: 'Detail notifikasi berhasil diambil.',
        );
    }

    public function markAsRead(
        Request $request,
        string $notification,
    ): JsonResponse {
        $result = $this->notificationService->markAsRead(
            user: $request->user(),
            id: $notification,
        );

        return ApiResponse::success(
            data: new NotificationResource($result),
            message: 'Notifikasi berhasil ditandai sebagai sudah dibaca.',
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllAsRead(
            user: $request->user(),
        );

        return ApiResponse::success(
            data: null,
            message: 'Semua notifikasi berhasil ditandai sebagai sudah dibaca.',
        );
    }

    public function destroy(
        Request $request,
        string $notification,
    ): JsonResponse {
        $this->notificationService->delete(
            user: $request->user(),
            id: $notification,
        );

        return ApiResponse::success(
            data: null,
            message: 'Notifikasi berhasil dihapus.',
        );
    }
}
