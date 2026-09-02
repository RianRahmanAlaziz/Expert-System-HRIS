<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeaveRequestService
{
    public function paginate(
        int $perPage = 15,
        ?int $employeeId = null,
        ?int $leaveTypeId = null,
        ?int $year = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        return LeaveRequest::query()
            ->with([
                'employee',
                'leaveType',
                'approvedBy',
            ])
            ->when(
                $employeeId !== null,
                function (Builder $query) use ($employeeId): void {
                    $query->where('employee_id', $employeeId);
                }
            )
            ->when(
                $leaveTypeId !== null,
                function (Builder $query) use ($leaveTypeId): void {
                    $query->where('leave_type_id', $leaveTypeId);
                }
            )
            ->when(
                $year !== null,
                function (Builder $query) use ($year): void {
                    $query->whereYear('start_date', $year);
                }
            )
            ->when(
                filled($status),
                function (Builder $query) use ($status): void {
                    $query->where('status', $status);
                }
            )
            ->latest()
            ->paginate($perPage);
    }

    public function getMyRequests(
        Employee $employee,
        int $perPage = 15,
        ?string $status = null,
        ?int $year = null,
    ): LengthAwarePaginator {
        return LeaveRequest::query()
            ->with([
                'leaveType',
                'approvedBy',
            ])
            ->where('employee_id', $employee->id)
            ->when(
                $year !== null,
                function (Builder $query) use ($year): void {
                    $query->whereYear('start_date', $year);
                }
            )
            ->when(
                filled($status),
                function (Builder $query) use ($status): void {
                    $query->where('status', $status);
                }
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): LeaveRequest
    {
        return LeaveRequest::query()
            ->with([
                'employee',
                'leaveType',
                'approvedBy',
            ])
            ->findOrFail($id);
    }

    public function create(
        Employee $employee,
        array $data,
    ): LeaveRequest {
        return DB::transaction(
            function () use ($employee, $data): LeaveRequest {
                $leaveType = LeaveType::query()
                    ->whereKey($data['leave_type_id'])
                    ->where('status', 'active')
                    ->first();

                if (!$leaveType) {
                    throw new ModelNotFoundException('Leave type tidak ditemukan atau sedang tidak aktif.');
                }

                $startDate = Carbon::parse($data['start_date']);
                $endDate = Carbon::parse($data['end_date']);

                if ($startDate->greaterThan($endDate)) {
                    throw new RuntimeException('Tanggal mulai cuti tidak boleh lebih besar dari tanggal selesai.');
                }

                if ($startDate->year !== $endDate->year) {
                    throw new RuntimeException('Pengajuan cuti tidak boleh melewati pergantian tahun.');
                }

                $hasOverlap = LeaveRequest::query()
                    ->where('employee_id', $employee->id)
                    ->whereIn('status', [
                        'pending',
                        'approved',
                    ])
                    ->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate)
                    ->exists();

                if ($hasOverlap) {
                    throw new RuntimeException('Pengajuan cuti bertabrakan dengan pengajuan cuti yang sudah ada.');
                }

                $totalDays = $startDate->diffInDays($endDate) + 1;

                $leaveRequest = LeaveRequest::query()->create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_days' => $totalDays,
                    'reason' => $data['reason'],
                    'status' => 'pending',
                ]);

                return $leaveRequest->load([
                    'employee',
                    'leaveType',
                    'approvedBy',
                ]);
            }
        );
    }

    public function approve(
        LeaveRequest $leaveRequest,
        User $approvedBy,
    ): LeaveRequest {
        return DB::transaction(
            function () use (
                $leaveRequest,
                $approvedBy
            ): LeaveRequest {
                $leaveRequest = LeaveRequest::query()
                    ->lockForUpdate()
                    ->findOrFail($leaveRequest->id);

                if ($leaveRequest->status !== 'pending') {
                    throw new RuntimeException('Leave request hanya dapat disetujui ketika status masih pending.');
                }

                $leaveBalance = LeaveBalance::query()
                    ->where('employee_id', $leaveRequest->employee_id)
                    ->where('leave_type_id', $leaveRequest->leave_type_id)
                    ->where('year',  Carbon::parse($leaveRequest->start_date)->year)
                    ->lockForUpdate()
                    ->first();

                if (!$leaveBalance) {
                    throw new RuntimeException('Leave balance untuk employee dan leave type tersebut tidak ditemukan.');
                }

                if (
                    (float) $leaveBalance->remaining_days
                    < (float) $leaveRequest->total_days
                ) {
                    throw new RuntimeException('Saldo cuti tidak mencukupi untuk pengajuan ini.');
                }

                $leaveBalance->used_days = (float) $leaveBalance->used_days + (float) $leaveRequest->total_days;

                $leaveBalance->remaining_days = (float) $leaveBalance->remaining_days - (float) $leaveRequest->total_days;

                $leaveBalance->save();

                $leaveRequest->update([
                    'status' => 'approved',
                    'approved_by' => $approvedBy->id,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ]);

                return $leaveRequest
                    ->refresh()
                    ->load([
                        'employee',
                        'leaveType',
                        'approvedBy',
                    ]);
            }
        );
    }

    public function reject(
        LeaveRequest $leaveRequest,
        User $rejectedBy,
        string $rejectionReason,
    ): LeaveRequest {
        return DB::transaction(
            function () use (
                $leaveRequest,
                $rejectedBy,
                $rejectionReason
            ): LeaveRequest {
                $leaveRequest = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);

                if ($leaveRequest->status !== 'pending') {
                    throw new RuntimeException('Leave request hanya dapat ditolak ketika status masih pending.');
                }

                $leaveRequest->update([
                    'status' => 'rejected',
                    'approved_by' => $rejectedBy->id,
                    'approved_at' => null,
                    'rejection_reason' => $rejectionReason,
                ]);

                return $leaveRequest
                    ->refresh()
                    ->load([
                        'employee',
                        'leaveType',
                        'approvedBy',
                    ]);
            }
        );
    }

    public function cancel(
        LeaveRequest $leaveRequest,
        Employee $employee,
    ): LeaveRequest {
        return DB::transaction(
            function () use (
                $leaveRequest,
                $employee
            ): LeaveRequest {
                $leaveRequest = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);

                if ($leaveRequest->employee_id !== $employee->id) {
                    throw new RuntimeException('Anda tidak dapat membatalkan pengajuan cuti milik employee lain.');
                }

                if ($leaveRequest->status !== 'pending') {
                    throw new RuntimeException('Leave request hanya dapat dibatalkan ketika status masih pending.');
                }

                $leaveRequest->update([
                    'status' => 'cancelled',
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejection_reason' => null,
                ]);

                return $leaveRequest
                    ->refresh()
                    ->load([
                        'employee',
                        'leaveType',
                        'approvedBy',
                    ]);
            }
        );
    }
}
