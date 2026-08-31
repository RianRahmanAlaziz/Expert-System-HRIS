<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use RuntimeException;

class AttendanceService
{
    /**
     * Clock in employee.
     */
    public function clockIn(Employee $employee): Attendance
    {
        $clockIn = now();

        $existingAttendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $clockIn->toDateString())
            ->first();

        if ($existingAttendance) {
            throw new RuntimeException('Anda sudah melakukan clock in hari ini.');
        }

        $lateMinutes = $this->calculateLateMinutes($clockIn);
        $status = $this->determineStatus($lateMinutes);

        return Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => $clockIn->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => null,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'working_minutes' => 0,
            'notes' => null,
        ]);
    }

    /**
     * Clock out employee.
     */
    public function clockOut(Employee $employee): Attendance
    {
        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', today())
            ->first();

        if (!$attendance) {
            throw new ModelNotFoundException(
                'Attendance hari ini tidak ditemukan. Silakan lakukan clock in terlebih dahulu.'
            );
        }

        if (!$attendance->clock_in) {
            throw new RuntimeException(
                'Anda belum melakukan clock in hari ini.'
            );
        }

        if ($attendance->clock_out) {
            throw new RuntimeException(
                'Anda sudah melakukan clock out hari ini.'
            );
        }

        $clockOut = now();

        $workingMinutes = $this->calculateWorkingMinutes(
            $attendance->clock_in,
            $clockOut
        );

        $attendance->update([
            'clock_out' => $clockOut,
            'working_minutes' => $workingMinutes,
        ]);

        return $attendance->fresh();
    }

    /**
     * Get attendance history.
     */
    public function getAll(
        array $filters,
        ?Employee $employee,
        bool $viewAll = false
    ): LengthAwarePaginator {
        $query = Attendance::query()
            ->with('employee')
            ->latest('attendance_date')
            ->latest('id');

        /*
         * Employee can only see their own attendance.
         */
        if (!$viewAll) {
            if (!$employee) {
                throw new RuntimeException(
                    'User tidak memiliki data employee.'
                );
            }

            $query->where('employee_id', $employee->id);
        }

        /*
         * Filter by start date.
         */
        if (!empty($filters['start_date'])) {
            $query->whereDate(
                'attendance_date',
                '>=',
                $filters['start_date']
            );
        }

        /*
         * Filter by end date.
         */
        if (!empty($filters['end_date'])) {
            $query->whereDate(
                'attendance_date',
                '<=',
                $filters['end_date']
            );
        }

        /*
         * Filter by status.
         */
        if (!empty($filters['status'])) {
            $query->where(
                'status',
                $filters['status']
            );
        }

        /*
         * Only users with view_all permission
         * can filter attendance by employee.
         */
        if (
            $viewAll &&
            !empty($filters['employee_id'])
        ) {
            $query->where(
                'employee_id',
                $filters['employee_id']
            );
        }

        return $query->paginate(15);
    }

    /**
     * Get attendance detail.
     */
    public function getById(
        int $id,
        ?Employee $employee,
        bool $viewAll = false
    ): Attendance {
        $query = Attendance::query()
            ->with('employee')
            ->whereKey($id);

        /*
         * Employee can only access their own attendance.
         */
        if (!$viewAll) {
            if (!$employee) {
                throw new RuntimeException(
                    'User tidak memiliki data employee.'
                );
            }

            $query->where('employee_id', $employee->id);
        }

        return $query->firstOrFail();
    }

    /**
     * Get attendance recap.
     */
    public function getRecap(
        array $filters,
        ?Employee $employee,
        bool $viewAll = false
    ): array {
        $query = Attendance::query();

        /*
         * Employee can only see their own recap.
         */
        if (!$viewAll) {
            if (!$employee) {
                throw new RuntimeException(
                    'User tidak memiliki data employee.'
                );
            }

            $query->where('employee_id', $employee->id);
        }

        /*
         * HR/Admin can filter by employee.
         */
        if (
            $viewAll &&
            !empty($filters['employee_id'])
        ) {
            $query->where(
                'employee_id',
                $filters['employee_id']
            );
        }

        /*
         * Date filters.
         */
        if (!empty($filters['start_date'])) {
            $query->whereDate(
                'attendance_date',
                '>=',
                $filters['start_date']
            );
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate(
                'attendance_date',
                '<=',
                $filters['end_date']
            );
        }

        /*
         * Status filter.
         */
        if (!empty($filters['status'])) {
            $query->where(
                'status',
                $filters['status']
            );
        }

        return [
            'total_days' => (clone $query)->count(),
            'present' => (clone $query)->where('status', 'present')->count(),
            'late' => (clone $query)->where('status', 'late')->count(),
            'absent' => (clone $query)->where('status', 'absent')->count(),
            'total_late_minutes' => (clone $query)->sum('late_minutes'),
            'total_working_minutes' => (clone $query)->sum('working_minutes'),
        ];
    }

    /**
     * Get attendance report.
     */
    public function getReport(
        array $filters
    ): array {
        $query = Attendance::query()
            ->with('employee')
            ->orderBy('employee_id')
            ->orderBy('attendance_date');

        /*
         * Date filters.
         */
        if (!empty($filters['start_date'])) {
            $query->whereDate(
                'attendance_date',
                '>=',
                $filters['start_date']
            );
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate(
                'attendance_date',
                '<=',
                $filters['end_date']
            );
        }

        /*
         * Status filter.
         */
        if (!empty($filters['status'])) {
            $query->where(
                'status',
                $filters['status']
            );
        }

        /*
         * Employee filter.
         */
        if (!empty($filters['employee_id'])) {
            $query->where(
                'employee_id',
                $filters['employee_id']
            );
        }

        $attendances = $query->get();

        return $attendances
            ->groupBy('employee_id')
            ->map(function ($employeeAttendances) {
                $employee = $employeeAttendances->first()->employee;

                return [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'employee_name' => trim(
                        $employee->first_name . ' ' . $employee->last_name
                    ),

                    'present' => $employeeAttendances->where('status', 'present')->count(),
                    'late' => $employeeAttendances->where('status', 'late')->count(),
                    'absent' => $employeeAttendances->where('status', 'absent')->count(),
                    'total_late_minutes' => $employeeAttendances->sum('late_minutes'),
                    'total_working_minutes' => $employeeAttendances->sum('working_minutes'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Calculate late minutes based on default work start.
     */
    private function calculateLateMinutes(Carbon $clockIn): int
    {
        $workStart = Carbon::parse(
            $clockIn->toDateString()
                . ' '
                . config('attendance.work_start')
        );

        if ($clockIn->lte($workStart)) {
            return 0;
        }

        return $workStart->diffInMinutes($clockIn);
    }

    /**
     * Determine attendance status.
     */
    private function determineStatus(int $lateMinutes): string
    {
        return $lateMinutes > 0
            ? 'late'
            : 'present';
    }

    /**
     * Calculate working minutes.
     */
    private function calculateWorkingMinutes(
        Carbon $clockIn,
        Carbon $clockOut
    ): int {
        return $clockIn->diffInMinutes($clockOut);
    }
}
