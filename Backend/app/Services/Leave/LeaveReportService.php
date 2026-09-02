<?php

namespace App\Services\Leave;

use App\Models\LeaveBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LeaveReportService
{
    public function paginate(
        int $perPage = 15,
        ?int $employeeId = null,
        ?int $leaveTypeId = null,
        ?int $year = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $requestCounts = DB::table('leave_requests')
            ->select(
                'employee_id',
                'leave_type_id',
                DB::raw('YEAR(start_date) as year'),
                DB::raw('COUNT(*) as total_requests'),
                DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests"),
                DB::raw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests"),
                DB::raw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests"),
                DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_requests"),
            )
            ->groupBy(
                'employee_id',
                'leave_type_id',
                DB::raw('YEAR(start_date)')
            );

        $statusColumnMap = [
            'pending' => 'pending_requests',
            'approved' => 'approved_requests',
            'rejected' => 'rejected_requests',
            'cancelled' => 'cancelled_requests',
        ];

        return LeaveBalance::query()
            ->join(
                'employees',
                'employees.id',
                '=',
                'leave_balances.employee_id'
            )
            ->join(
                'leave_types',
                'leave_types.id',
                '=',
                'leave_balances.leave_type_id'
            )
            ->leftJoinSub(
                $requestCounts,
                'request_counts',
                function ($join): void {
                    $join
                        ->on(
                            'request_counts.employee_id',
                            '=',
                            'leave_balances.employee_id'
                        )
                        ->on(
                            'request_counts.leave_type_id',
                            '=',
                            'leave_balances.leave_type_id'
                        )
                        ->on(
                            'request_counts.year',
                            '=',
                            'leave_balances.year'
                        );
                }
            )
            ->select([
                'leave_balances.employee_id',
                'employees.employee_number',
                DB::raw(
                    "TRIM(CONCAT(
                        employees.first_name,
                        ' ',
                        COALESCE(employees.last_name, '')
                    )) as employee_name"
                ),

                'leave_balances.leave_type_id',
                'leave_types.name as leave_type',

                'leave_balances.year',

                'leave_balances.allocated_days',
                'leave_balances.used_days',
                'leave_balances.remaining_days',

                DB::raw('COALESCE(request_counts.total_requests, 0) as total_requests'),
                DB::raw('COALESCE(request_counts.pending_requests, 0) as pending_requests'),
                DB::raw('COALESCE(request_counts.approved_requests, 0) as approved_requests'),
                DB::raw('COALESCE(request_counts.rejected_requests, 0) as rejected_requests'),
                DB::raw('COALESCE(request_counts.cancelled_requests, 0) as cancelled_requests'),
            ])
            ->when(
                $employeeId !== null,
                function (Builder $query) use ($employeeId): void {
                    $query->where(
                        'leave_balances.employee_id',
                        $employeeId
                    );
                }
            )
            ->when(
                $leaveTypeId !== null,
                function (Builder $query) use ($leaveTypeId): void {
                    $query->where(
                        'leave_balances.leave_type_id',
                        $leaveTypeId
                    );
                }
            )
            ->when(
                $year !== null,
                function (Builder $query) use ($year): void {
                    $query->where('leave_balances.year', $year);
                }
            )
            ->when(
                $status !== null,
                function (Builder $query) use (
                    $status,
                    $statusColumnMap
                ): void {
                    $query->where(
                        'request_counts.' . $statusColumnMap[$status],
                        '>',
                        0
                    );
                }
            )
            ->orderByDesc('leave_balances.year')
            ->orderByDesc('leave_balances.id')
            ->paginate($perPage);
    }
}
