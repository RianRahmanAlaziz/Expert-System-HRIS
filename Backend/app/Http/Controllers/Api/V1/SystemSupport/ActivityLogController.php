<?php

namespace App\Http\Controllers\Api\V1\SystemSupport;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SystemSupport\ActivityLogResource;
use App\Services\SystemSupport\ActivityLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ActivityLogController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:activity_log.view', only: ['index', 'show']),
        ];
    }

    /**
     * Display a listing of activity logs.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max($request->integer('per_page', 15), 1),
            100,
        );

        $search = trim(
            (string) $request->query('search', ''),
        );

        $userId = $request->integer('user_id');
        $module = $request->query('module');
        $action = $request->query('action');

        $activityLogs = $this->activityLogService->paginate(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            userId: $userId > 0 ? $userId : null,
            module: $module,
            action: $action,
        );

        return ApiResponse::success(
            data: ActivityLogResource::collection($activityLogs),
            message: 'Daftar activity log berhasil diambil.',
            meta: [
                'pagination' => [
                    'current_page' => $activityLogs->currentPage(),
                    'last_page' => $activityLogs->lastPage(),
                    'per_page' => $activityLogs->perPage(),
                    'total' => $activityLogs->total(),
                    'from' => $activityLogs->firstItem(),
                    'to' => $activityLogs->lastItem(),
                ],
            ],
        );
    }

    /**
     * Display the specified activity log.
     */
    public function show(int $activityLog): JsonResponse
    {
        $activityLog = $this->activityLogService->findById($activityLog);

        return ApiResponse::success(
            data: ActivityLogResource::make($activityLog),
            message: 'Detail activity log berhasil diambil.',
        );
    }
}
