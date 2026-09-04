<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Competency\CompetencyController;
use App\Http\Controllers\Api\V1\Competency\CompetencyLevelController;
use App\Http\Controllers\Api\V1\Competency\EmployeeCompetencyController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Leave\LeaveBalanceController;
use App\Http\Controllers\Api\V1\Leave\LeaveReportController;
use App\Http\Controllers\Api\V1\Leave\LeaveRequestController;
use App\Http\Controllers\Api\V1\Leave\LeaveTypeController;
use App\Http\Controllers\Api\V1\Performance\PerformanceHistoryController;
use App\Http\Controllers\Api\V1\Performance\PerformanceIndicatorController;
use App\Http\Controllers\Api\V1\Performance\PerformancePeriodController;
use App\Http\Controllers\Api\V1\Performance\PerformanceReportController;
use App\Http\Controllers\Api\V1\Performance\PerformanceReviewController;
use App\Http\Controllers\Api\V1\Performance\PerformanceReviewItemController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\Training\TrainingController;
use App\Http\Controllers\Api\V1\Training\TrainingParticipantController;
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

    Route::prefix('performance')->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Performance Period
        |--------------------------------------------------------------------------
        */
        Route::get('periods', [PerformancePeriodController::class, 'index'])->middleware('permission:performance-period.view');
        Route::post('periods',  [PerformancePeriodController::class, 'store'])->middleware('permission:performance-period.create');
        Route::get('periods/{performancePeriod}',  [PerformancePeriodController::class, 'show'])->middleware('permission:performance-period.view');
        Route::put('periods/{performancePeriod}', [PerformancePeriodController::class, 'update'])->middleware('permission:performance-period.update');
        Route::patch('periods/{performancePeriod}',  [PerformancePeriodController::class, 'update'])->middleware('permission:performance-period.update');
        Route::delete('periods/{performancePeriod}',   [PerformancePeriodController::class, 'destroy'])->middleware('permission:performance-period.delete');

        /*
        |--------------------------------------------------------------------------
        | Performance Indicator
        |--------------------------------------------------------------------------
        */
        Route::get('indicators/active', [PerformanceIndicatorController::class, 'active'])->middleware('permission:performance-indicator.view');
        Route::get('indicators',  [PerformanceIndicatorController::class, 'index'])->middleware('permission:performance-indicator.view');
        Route::post('indicators', [PerformanceIndicatorController::class, 'store'])->middleware('permission:performance-indicator.create');
        Route::get('indicators/{performanceIndicator}',  [PerformanceIndicatorController::class, 'show'])->middleware('permission:performance-indicator.view');
        Route::put('indicators/{performanceIndicator}',  [PerformanceIndicatorController::class, 'update'])->middleware('permission:performance-indicator.update');
        Route::patch('indicators/{performanceIndicator}', [PerformanceIndicatorController::class, 'update'])->middleware('permission:performance-indicator.update');
        Route::delete('indicators/{performanceIndicator}', [PerformanceIndicatorController::class, 'destroy'])->middleware('permission:performance-indicator.delete');

        /*
        |--------------------------------------------------------------------------
        | Performance Review
        |--------------------------------------------------------------------------
        */
        Route::get('reviews',  [PerformanceReviewController::class, 'index'])->middleware('permission:performance-review.view');
        Route::post('reviews',  [PerformanceReviewController::class, 'store'])->middleware('permission:performance-review.create');
        Route::get('reviews/{performanceReview}',  [PerformanceReviewController::class, 'show'])->middleware('permission:performance-review.view');
        Route::put('reviews/{performanceReview}',   [PerformanceReviewController::class, 'update'])->middleware('permission:performance-review.update');
        Route::patch('reviews/{performanceReview}',  [PerformanceReviewController::class, 'update'])->middleware('permission:performance-review.update');
        Route::post('reviews/{performanceReview}/calculate',    [PerformanceReviewController::class, 'calculate'])->middleware('permission:performance-review.update');
        Route::post('reviews/{performanceReview}/submit',  [PerformanceReviewController::class, 'submit'])->middleware('permission:performance-review.submit');
        Route::post('reviews/{performanceReview}/approve',  [PerformanceReviewController::class, 'approve'])->middleware('permission:performance-review.approve');
        Route::post('reviews/{performanceReview}/reject',   [PerformanceReviewController::class, 'reject'])->middleware('permission:performance-review.reject');
        Route::delete('reviews/{performanceReview}',  [PerformanceReviewController::class, 'destroy'])->middleware('permission:performance-review.delete');

        /*
        |--------------------------------------------------------------------------
        | Performance Review Items
        |--------------------------------------------------------------------------
        */
        Route::scopeBindings()->group(function (): void {
            Route::get('reviews/{performanceReview}/items',   [PerformanceReviewItemController::class, 'index'])->middleware('permission:performance-review.view');
            Route::post('reviews/{performanceReview}/items',  [PerformanceReviewItemController::class, 'store'])->middleware('permission:performance-review.update');
            Route::get('reviews/{performanceReview}/items/{performanceReviewItem}',   [PerformanceReviewItemController::class, 'show'])->middleware('permission:performance-review.view');
            Route::put('reviews/{performanceReview}/items/{performanceReviewItem}',   [PerformanceReviewItemController::class, 'update'])->middleware('permission:performance-review.update');
            Route::patch('reviews/{performanceReview}/items/{performanceReviewItem}',   [PerformanceReviewItemController::class, 'update'])->middleware('permission:performance-review.update');
            Route::delete('reviews/{performanceReview}/items/{performanceReviewItem}',  [PerformanceReviewItemController::class, 'destroy'])->middleware('permission:performance-review.delete');
        });

        /*
        |--------------------------------------------------------------------------
        | Performance History
        |--------------------------------------------------------------------------
        */
        Route::get('history',  [PerformanceHistoryController::class, 'index'])->middleware('permission:performance-review.view');
        Route::get('history/employees/{employee}',   [PerformanceHistoryController::class, 'employee'])->middleware('permission:performance-review.view');

        /*
        |--------------------------------------------------------------------------
        | Performance Report
        |--------------------------------------------------------------------------
        */
        Route::get('reports',  [PerformanceReportController::class, 'index'])->middleware('permission:performance-report.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Competency
    |--------------------------------------------------------------------------
    */

    Route::middleware('permission:competency.view')->group(function (): void {
        Route::get('/competencies', [CompetencyController::class, 'index']);
        Route::get('/competencies/{competency}', [CompetencyController::class, 'show']);
    });

    Route::post('/competencies', [CompetencyController::class, 'store'])->middleware('permission:competency.create');
    Route::put('/competencies/{competency}', [CompetencyController::class, 'update'])->middleware('permission:competency.update');
    Route::patch('/competencies/{competency}', [CompetencyController::class, 'update'])->middleware('permission:competency.update');
    Route::delete('/competencies/{competency}', [CompetencyController::class, 'destroy'])->middleware('permission:competency.delete');

    /*
    |--------------------------------------------------------------------------
    | Competency Level
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:competency-level.view')->group(function (): void {
        Route::get('/competency-levels', [CompetencyLevelController::class, 'index']);
        Route::get('/competency-levels/{competencyLevel}', [CompetencyLevelController::class, 'show']);
    });
    Route::post('/competency-levels', [CompetencyLevelController::class, 'store'])->middleware('permission:competency-level.create');
    Route::put('/competency-levels/{competencyLevel}', [CompetencyLevelController::class, 'update'])->middleware('permission:competency-level.update');
    Route::patch('/competency-levels/{competencyLevel}', [CompetencyLevelController::class, 'update'])->middleware('permission:competency-level.update');
    Route::delete('/competency-levels/{competencyLevel}', [CompetencyLevelController::class, 'destroy'])->middleware('permission:competency-level.delete');

    /*
    |--------------------------------------------------------------------------
    | Employee Competency
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:employee-competency.view')->group(function (): void {
        Route::get('/employee-competencies', [EmployeeCompetencyController::class, 'index']);
        Route::get('/employee-competencies/{employeeCompetency}', [EmployeeCompetencyController::class, 'show']);
    });

    Route::post('/employee-competencies', [EmployeeCompetencyController::class, 'store'])->middleware('permission:employee-competency.create');
    Route::put('/employee-competencies/{employeeCompetency}', [EmployeeCompetencyController::class, 'update'])->middleware('permission:employee-competency.update');
    Route::patch('/employee-competencies/{employeeCompetency}', [EmployeeCompetencyController::class, 'update'])->middleware('permission:employee-competency.update');
    Route::delete('/employee-competencies/{employeeCompetency}', [EmployeeCompetencyController::class, 'destroy'])->middleware('permission:employee-competency.delete');

    // Training
    Route::apiResource('trainings', TrainingController::class);
    Route::patch('trainings/{training}/status',   [TrainingController::class, 'updateStatus']);

    Route::get('training-participants/history/{employeeId}',  [TrainingParticipantController::class, 'history']);
    Route::apiResource('training-participants', TrainingParticipantController::class);
    Route::post('training-participants/{trainingParticipant}/evaluate', [TrainingParticipantController::class, 'evaluate']);
});
