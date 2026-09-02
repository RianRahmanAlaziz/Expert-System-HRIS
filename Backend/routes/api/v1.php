<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LeaveBalanceController;
use App\Http\Controllers\Api\V1\LeaveReportController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\LeaveTypeController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::prefix('auth')->name('auth.')->group(function (): void {

    Route::middleware('throttle:5,1')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::get('/me', [ProfileController::class, 'show'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:role.view')->group(function (): void {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}', [RoleController::class, 'show']);
    });

    Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permission.view');

    /*
    |--------------------------------------------------------------------------
    | Departments
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:department.view')->group(function (): void {
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::get('/departments/{department}', [DepartmentController::class, 'show']);
    });

    Route::post('/departments', [DepartmentController::class, 'store'])->middleware('permission:department.create');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->middleware('permission:department.update');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:department.delete');

    /*
    |--------------------------------------------------------------------------
    | Positions
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:position.view')->group(function (): void {
        Route::get('/positions', [PositionController::class, 'index']);
        Route::get('/positions/{position}', [PositionController::class, 'show']);
    });
    Route::post('/positions', [PositionController::class, 'store'])->middleware('permission:position.create');
    Route::put('/positions/{position}', [PositionController::class, 'update'])->middleware('permission:position.update');
    Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->middleware('permission:position.delete');

    /*
    |--------------------------------------------------------------------------
    | Employees
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:employee.view')->group(function (): void {
        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
    });

    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('permission:employee.create');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:employee.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:employee.delete');

    /*
    |--------------------------------------------------------------------------
    | Attendance
    |--------------------------------------------------------------------------
    */
    Route::get('/attendances/recap', [AttendanceController::class, 'recap'])->middleware('permission:attendance.view_all');
    Route::get('/attendances/report', [AttendanceController::class, 'report'])->middleware('permission:attendance.report');

    Route::middleware('permission:attendance.view')->group(function (): void {
        Route::get('/attendances', [AttendanceController::class, 'index']);
        Route::get('/attendances/{attendance}', [AttendanceController::class, 'show']);
    });

    Route::post('/attendances/clock-in', [AttendanceController::class, 'clockIn'])->middleware('permission:attendance.clock_in');
    Route::post('/attendances/clock-out', [AttendanceController::class, 'clockOut'])->middleware('permission:attendance.clock_out');

    /*
    |--------------------------------------------------------------------------
    | Leave
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:leave_type.view')->group(function () {
        Route::get('/leave-types', [LeaveTypeController::class, 'index']);
        Route::get('/leave-types/{leaveType}', [LeaveTypeController::class, 'show']);
    });

    Route::post('/leave-types', [LeaveTypeController::class, 'store'])->middleware('permission:leave_type.create');
    Route::put('/leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->middleware('permission:leave_type.update');
    Route::delete('/leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->middleware('permission:leave_type.delete');

    Route::get('/leave-balances/me', [LeaveBalanceController::class, 'me'])->middleware('permission:leave_balance.view');

    Route::middleware('permission:leave_balance.view_all')->group(function () {
        Route::get('/leave-balances', [LeaveBalanceController::class, 'index']);
        Route::get('/leave-balances/{employee}', [LeaveBalanceController::class, 'employee']);
    });

    Route::middleware('permission:leave_request.view')->group(function () {
        Route::get('/leave-requests/me', [LeaveRequestController::class, 'me']);
        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);
    });

    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->middleware('permission:leave_request.create');
    Route::post('/leave-requests/{leaveRequest}/approve',  [LeaveRequestController::class, 'approve'])->middleware('permission:leave_request.approve');
    Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->middleware('permission:leave_request.reject');
    Route::post('/leave-requests/{leaveRequest}/cancel',  [LeaveRequestController::class, 'cancel'])->middleware('permission:leave_request.cancel');
    Route::get('/leave-reports', [LeaveReportController::class, 'index'])->middleware('permission:leave_report.view');
});
