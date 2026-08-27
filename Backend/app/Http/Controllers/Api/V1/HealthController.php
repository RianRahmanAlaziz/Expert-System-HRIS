<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'service' => config('app.name'),
                'api_version' => 'v1',
                'timestamp' => now()->toIso8601String(),
            ],
            message: 'API is running',
        );
    }
}
