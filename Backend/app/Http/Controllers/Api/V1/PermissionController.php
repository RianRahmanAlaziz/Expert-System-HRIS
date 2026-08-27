<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'guard_name',
            ]);

        return ApiResponse::success(
            data: $permissions,
            message: 'Daftar permission berhasil diambil.',
        );
    }
}
