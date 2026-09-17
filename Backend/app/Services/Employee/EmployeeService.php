<?php

namespace App\Services\Employee;

use App\Authorization\EmployeeScope;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\SystemSupport\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected EmployeeScope $employeeScope,
    ) {}

    public function paginate(
        User $user,
        int $perPage = 15,
        ?string $search = null,
        ?int $departmentId = null,
        ?int $positionId = null,
        ?int $managerId = null,
        ?string $employmentType = null,
        ?string $employmentStatus = null,
    ): LengthAwarePaginator {
        $query = Employee::query();

        $this->employeeScope->apply(
            query: $query,
            user: $user,
        );

        return $query
            ->with([
                'user',
                'department',
                'position',
                'manager',
            ])
            ->when(
                filled($search),
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    });
                },
            )
            ->when(
                $departmentId !== null,
                fn(Builder $query) => $query->where(
                    'department_id',
                    $departmentId,
                ),
            )
            ->when(
                $positionId !== null,
                fn(Builder $query) => $query->where(
                    'position_id',
                    $positionId,
                ),
            )
            ->when(
                $managerId !== null,
                fn(Builder $query) => $query->where(
                    'manager_id',
                    $managerId,
                ),
            )
            ->when(
                filled($employmentType),
                fn(Builder $query) => $query->where(
                    'employment_type',
                    $employmentType,
                ),
            )
            ->when(
                filled($employmentStatus),
                fn(Builder $query) => $query->where(
                    'employment_status',
                    $employmentStatus,
                ),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(
        User $user,
        int $id,
    ): Employee {
        $query = Employee::query();

        $this->employeeScope->apply(
            query: $query,
            user: $user,
        );

        return $query
            ->with([
                'user',
                'department',
                'position',
                'manager',
                'subordinates',
                'employmentHistories.department',
                'employmentHistories.position',
                'employmentHistories.manager',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            $department = $this->getActiveDepartment(
                departmentId: (int) $data['department_id'],
            );

            $position = $this->getActivePosition(
                positionId: (int) $data['position_id'],
            );

            $managerId = $data['manager_id'] ?? null;

            if ($managerId !== null) {
                $this->validateManagerHierarchy(
                    employee: null,
                    managerId: (int) $managerId,
                );
            }

            $employee = Employee::query()->create([
                'user_id' => $data['user_id'] ?? null,
                'department_id' => $department->id,
                'position_id' => $position->id,
                'manager_id' => $managerId,
                'employee_number' => $data['employee_number'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'join_date' => $data['join_date'],
                'employment_type' => $data['employment_type'],
                'employment_status' => $data['employment_status'],
            ]);

            $employee->employmentHistories()->create([
                'department_id' => $employee->department_id,
                'position_id' => $employee->position_id,
                'manager_id' => $employee->manager_id,
                'employment_type' => $employee->employment_type,
                'start_date' => $employee->join_date,
                'end_date' => null,
                'reason' => $data['history_reason'] ?? 'Initial employment',
                'notes' => $data['history_notes'] ?? null,
            ]);

            $this->activityLogService->log(
                action: 'create',
                module: 'employee',
                target: $employee,
                newValues: $this->employeeAuditValues($employee),
            );

            return $employee->load([
                'user',
                'department',
                'position',
                'manager',
                'employmentHistories',
            ]);
        });
    }

    public function update(
        Employee $employee,
        array $data,
    ): Employee {
        return DB::transaction(function () use ($employee, $data): Employee {
            /*
             * Lock employee row so two concurrent employment changes
             * cannot create conflicting histories.
             */
            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->id);

            $oldValues = $this->employeeAuditValues($employee);

            /*
             * History metadata belongs to EmploymentHistory,
             * not Employee.
             */
            $historyReason = $data['history_reason'] ?? null;
            $historyNotes = $data['history_notes'] ?? null;
            $effectiveDate = isset($data['effective_date'])
                ? Carbon::parse($data['effective_date'])
                : null;

            unset(
                $data['history_reason'],
                $data['history_notes'],
                $data['effective_date'],
            );

            /*
             * Determine whether the employee's employment structure
             * actually changed.
             */
            $employmentChanged = $this->employmentDataChanged(
                employee: $employee,
                data: $data,
            );

            /*
             * Validate effective_date only when an employment
             * change actually occurs.
             */
            if ($employmentChanged && $effectiveDate === null) {
                throw ValidationException::withMessages([
                    'effective_date' => [
                        'Tanggal efektif wajib diisi ketika data kepegawaian berubah.',
                    ],
                ]);
            }

            if (! $employmentChanged && $effectiveDate !== null) {
                throw ValidationException::withMessages([
                    'effective_date' => [
                        'Tanggal efektif hanya dapat digunakan ketika data kepegawaian berubah.',
                    ],
                ]);
            }

            /*
             * Validate target department only when it is being changed.
             */
            if (
                array_key_exists('department_id', $data)
                && (int) $data['department_id'] !== (int) $employee->department_id
            ) {
                $this->getActiveDepartment(
                    departmentId: (int) $data['department_id'],
                );
            }

            /*
             * Validate target position only when it is being changed.
             */
            if (
                array_key_exists('position_id', $data)
                && (int) $data['position_id'] !== (int) $employee->position_id
            ) {
                $this->getActivePosition(
                    positionId: (int) $data['position_id'],
                );
            }

            /*
             * Validate manager hierarchy when manager changes.
             */
            if (
                array_key_exists('manager_id', $data)
                && $this->managerChanged(
                    employee: $employee,
                    managerId: $data['manager_id'],
                )
            ) {
                $managerId = $data['manager_id'] !== null
                    ? (int) $data['manager_id']
                    : null;

                if ($managerId !== null) {
                    $this->validateManagerHierarchy(
                        employee: $employee,
                        managerId: $managerId,
                    );
                }
            }

            /*
             * Update only Employee attributes.
             */
            $employee->update($data);

            /*
             * If employment-related data changed, update the
             * employment timeline.
             */
            if ($employmentChanged) {
                $this->updateEmploymentHistory(
                    employee: $employee,
                    effectiveDate: $effectiveDate,
                    reason: $historyReason,
                    notes: $historyNotes,
                );
            }

            $employee->refresh();

            $newValues = $this->employeeAuditValues($employee);

            if ($employmentChanged) {
                $newValues['effective_date'] = $effectiveDate?->toDateString();
                $newValues['history_reason'] = $historyReason;
                $newValues['history_notes'] = $historyNotes;
            }

            /*
             * ActivityLog is intentionally inside the same transaction.
             * If logging fails, the employee/history changes rollback.
             */
            $this->activityLogService->log(
                action: 'update',
                module: 'employee',
                target: $employee,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $employee->load([
                'user',
                'department',
                'position',
                'manager',
                'employmentHistories.department',
                'employmentHistories.position',
                'employmentHistories.manager',
            ]);
        });
    }

    public function delete(Employee $employee): void
    {
        DB::transaction(function () use ($employee): void {
            $employee = Employee::query()
                ->lockForUpdate()
                ->findOrFail($employee->id);

            $oldValues = $this->employeeAuditValues($employee);

            $employee->delete();

            $this->activityLogService->log(
                action: 'delete',
                module: 'employee',
                target: $employee,
                oldValues: $oldValues,
            );
        });
    }

    /**
     * Check whether employment-related attributes changed.
     */
    private function employmentDataChanged(
        Employee $employee,
        array $data,
    ): bool {
        $employmentFields = [
            'department_id',
            'position_id',
            'manager_id',
            'employment_type',
        ];

        foreach ($employmentFields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $oldValue = $employee->{$field};
            $newValue = $data[$field];

            if ($field !== 'manager_id') {
                if ((string) $oldValue !== (string) $newValue) {
                    return true;
                }

                continue;
            }

            if (
                ($oldValue === null && $newValue !== null)
                || ($oldValue !== null && $newValue === null)
                || (
                    $oldValue !== null
                    && $newValue !== null
                    && (int) $oldValue !== (int) $newValue
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether manager actually changed.
     */
    private function managerChanged(
        Employee $employee,
        mixed $managerId,
    ): bool {
        if ($managerId === null && $employee->manager_id === null) {
            return false;
        }

        if ($managerId === null || $employee->manager_id === null) {
            return true;
        }

        return (int) $managerId !== (int) $employee->manager_id;
    }

    /**
     * Update the employment history timeline.
     */
    private function updateEmploymentHistory(
        Employee $employee,
        Carbon $effectiveDate,
        ?string $reason,
        ?string $notes,
    ): void {
        $currentHistory = $employee
            ->employmentHistories()
            ->whereNull('end_date')
            ->latest('start_date')
            ->lockForUpdate()
            ->first();

        if (
            $currentHistory !== null
            && $effectiveDate->lessThanOrEqualTo($currentHistory->start_date)
        ) {
            throw ValidationException::withMessages([
                'effective_date' => [
                    'Tanggal efektif harus setelah tanggal mulai riwayat kepegawaian saat ini.',
                ],
            ]);
        }

        if ($currentHistory !== null) {
            $currentHistory->update([
                'end_date' => $effectiveDate->copy()->subDay(),
            ]);
        }

        $employee->employmentHistories()->create([
            'department_id' => $employee->department_id,
            'position_id' => $employee->position_id,
            'manager_id' => $employee->manager_id,
            'employment_type' => $employee->employment_type,
            'start_date' => $effectiveDate,
            'end_date' => null,
            'reason' => $reason ?? 'Employment data change',
            'notes' => $notes,
        ]);
    }

    /**
     * Get an active department.
     */
    private function getActiveDepartment(int $departmentId): Department
    {
        $department = Department::query()
            ->whereKey($departmentId)
            ->where('is_active', true)
            ->first();

        if ($department === null) {
            throw ValidationException::withMessages([
                'department_id' => [
                    'Department tidak ditemukan atau tidak aktif.',
                ],
            ]);
        }

        return $department;
    }

    /**
     * Get an active position.
     */
    private function getActivePosition(int $positionId): Position
    {
        $position = Position::query()
            ->whereKey($positionId)
            ->where('is_active', true)
            ->first();

        if ($position === null) {
            throw ValidationException::withMessages([
                'position_id' => [
                    'Position tidak ditemukan atau tidak aktif.',
                ],
            ]);
        }

        return $position;
    }

    /**
     * Prevent self-reference and circular manager hierarchy.
     *
     * Example:
     *
     * A -> B
     * B -> C
     * C -> A
     *
     * is rejected.
     */
    private function validateManagerHierarchy(
        ?Employee $employee,
        int $managerId,
    ): void {
        $employeeId = $employee?->id;

        if (
            $employeeId !== null
            && $managerId === $employeeId
        ) {
            throw ValidationException::withMessages([
                'manager_id' => [
                    'Employee tidak dapat menjadi manager untuk dirinya sendiri.',
                ],
            ]);
        }

        $manager = Employee::query()
            ->whereKey($managerId)
            ->first();

        if ($manager === null) {
            throw ValidationException::withMessages([
                'manager_id' => [
                    'Manager tidak ditemukan.',
                ],
            ]);
        }

        if ($employeeId === null) {
            return;
        }

        /*
         * Walk upward through the manager hierarchy.
         */
        $visited = [];

        while ($manager !== null) {
            if (isset($visited[$manager->id])) {
                throw ValidationException::withMessages([
                    'manager_id' => [
                        'Struktur manager yang dipilih membentuk circular hierarchy.',
                    ],
                ]);
            }

            $visited[$manager->id] = true;

            if ((int) $manager->id === (int) $employeeId) {
                throw ValidationException::withMessages([
                    'manager_id' => [
                        'Manager yang dipilih akan membentuk circular hierarchy.',
                    ],
                ]);
            }

            if ($manager->manager_id === null) {
                break;
            }

            $manager = Employee::query()
                ->whereKey($manager->manager_id)
                ->first();
        }
    }

    /**
     * Return fields used by ActivityLog.
     */
    private function employeeAuditValues(
        Employee $employee,
    ): array {
        return [
            'user_id' => $employee->user_id,
            'department_id' => $employee->department_id,
            'position_id' => $employee->position_id,
            'manager_id' => $employee->manager_id,
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'gender' => $employee->gender,
            'birth_date' => $employee->birth_date?->toDateString(),
            'phone' => $employee->phone,
            'address' => $employee->address,
            'join_date' => $employee->join_date?->toDateString(),
            'employment_type' => $employee->employment_type,
            'employment_status' => $employee->employment_status,
        ];
    }
}
