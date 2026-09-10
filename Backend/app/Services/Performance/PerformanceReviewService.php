<?php

namespace App\Services\Performance;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PerformanceReviewService
{
    public function __construct(
        protected PerformanceScoreService $scoreService
    ) {}

    public function paginate(
        User $user,
        int $perPage = 15,
        string $search = '',
        ?int $employeeId = null,
        ?int $performancePeriodId = null,
        ?string $reviewType = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = PerformanceReview::query()
            ->with([
                'employee',
                'period',
                'reviewer',
                'items.indicator',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->whereHas(
                                'employee',
                                function ($query) use ($search): void {
                                    $query
                                        ->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhere('employee_number', 'like', "%{$search}%");
                                },
                            )->orWhereHas(
                                'reviewer',
                                function ($query) use ($search): void {
                                    $query->where('name', 'like', "%{$search}%");
                                },
                            );
                    });
                },
            )->when(
                $employeeId !== null,
                fn($query) => $query->where('employee_id', $employeeId),
            )->when(
                $performancePeriodId !== null,
                fn($query) => $query->where('performance_period_id', $performancePeriodId),
            )->when(
                $reviewType !== null,
                fn($query) => $query->where('review_type',   $reviewType),
            )->when(
                $status !== null,
                fn($query) => $query->where('status',   $status)
            );

        if (
            $user->hasAnyRole([
                'super-admin',
                'admin',
                'hr-admin',
            ])
        ) {
            return $query->latest('id')->paginate($perPage);
        }

        if ($user->hasRole('manager')) {
            return $query
                ->whereHas(
                    'employee',
                    function ($query) use ($user): void {
                        $query->whereHas(
                            'manager',
                            function ($query) use ($user): void {
                                $query->where('user_id',  $user->id);
                            },
                        );
                    },
                )->latest('id')->paginate($perPage);
        }

        if ($user->hasRole('employee')) {
            return $query
                ->whereHas(
                    'employee',
                    function ($query) use ($user): void {
                        $query->where('user_id',   $user->id);
                    },
                )->latest('id')->paginate($perPage);
        }

        return $query->whereRaw('1 = 0')->latest('id')->paginate($perPage);
    }

    public function getById(int $id): PerformanceReview
    {
        return PerformanceReview::query()
            ->with([
                'employee',
                'period',
                'reviewer',
                'items.indicator',
            ])
            ->findOrFail($id);
    }

    public function create(
        User $user,
        array $data
    ): PerformanceReview {
        return DB::transaction(function () use ($user, $data) {

            $employee = Employee::with('manager')
                ->findOrFail($data['employee_id']);

            if ($user->hasRole('employee')) {
                if ($employee->user_id !== $user->id) {
                    throw new InvalidArgumentException(
                        'Employee hanya dapat membuat performance review untuk dirinya sendiri.'
                    );
                }

                if ($data['review_type'] !== 'self') {
                    throw new InvalidArgumentException(
                        'Employee hanya dapat membuat self review.'
                    );
                }
            }

            if ($user->hasRole('manager')) {
                if ($employee->manager?->user_id !== $user->id) {
                    throw new InvalidArgumentException(
                        'Manager hanya dapat membuat review untuk direct report.'
                    );
                }

                if ($data['review_type'] !== 'manager') {
                    throw new InvalidArgumentException(
                        'Manager hanya dapat membuat manager review.'
                    );
                }
            }

            if (
                $user->hasAnyRole([
                    'super-admin',
                    'admin',
                    'hr-admin',
                ]) === false &&
                !$user->hasAnyRole([
                    'manager',
                    'employee',
                ])
            ) {
                throw new InvalidArgumentException(
                    'User tidak memiliki akses untuk membuat performance review.'
                );
            }

            $data['reviewer_id'] = $user->id;
            $data['status'] = 'draft';

            $review = PerformanceReview::create($data);

            return $review->load([
                'employee',
                'period',
                'reviewer',
                'items.indicator',
            ]);
        });
    }

    public function update(
        User $user,
        PerformanceReview $review,
        array $data
    ): PerformanceReview {
        if ($review->status === 'approved') {
            throw new InvalidArgumentException('Performance review yang sudah approved tidak dapat diubah.');
        }

        $this->authorizeUserAccess($user, $review);

        $review->update($data);

        return $review->refresh()->load([
            'employee',
            'period',
            'reviewer',
            'items.indicator',
        ]);
    }

    public function delete(
        User $user,
        PerformanceReview $review
    ): void {
        if ($review->status === 'approved') {
            throw new InvalidArgumentException('Performance review yang sudah approved tidak dapat dihapus.');
        }

        $this->authorizeUserAccess($user, $review);

        $review->delete();
    }

    public function calculateScore(
        User $user,
        PerformanceReview $review
    ): PerformanceReview {
        if ($review->status === 'approved') {
            throw new InvalidArgumentException('Performance review yang sudah approved tidak dapat dihitung ulang.');
        }

        $this->authorizeUserAccess($user, $review);

        return $this->scoreService->calculateAndSave($review);
    }

    public function submit(
        User $user,
        PerformanceReview $review
    ): PerformanceReview {
        if ($review->status !== 'draft') {
            throw new InvalidArgumentException('Hanya performance review dengan status draft yang dapat disubmit.');
        }

        $this->authorizeUserAccess($user, $review);

        $review->loadMissing('items.indicator');

        if ($review->items->isEmpty()) {
            throw new InvalidArgumentException('Performance review belum memiliki indikator.');
        }

        foreach ($review->items as $item) {
            if ($item->score === null) {
                throw new InvalidArgumentException('Semua indikator harus memiliki score sebelum review disubmit.');
            }
        }

        $review->overall_score = $this->scoreService->calculate($review);
        $review->status = 'submitted';
        $review->review_date ??= now()->toDateString();
        $review->save();

        return $review->refresh()->load([
            'employee',
            'period',
            'reviewer',
            'items.indicator',
        ]);
    }

    public function approve(
        User $user,
        PerformanceReview $review
    ): PerformanceReview {
        if ($review->status !== 'submitted') {
            throw new InvalidArgumentException('Hanya performance review yang sudah submitted yang dapat diapprove.');
        }

        $this->authorizeUserAccess($user, $review);

        $review->status = 'approved';
        $review->save();

        return $review->refresh()->load([
            'employee',
            'period',
            'reviewer',
            'items.indicator',
        ]);
    }

    public function reject(
        User $user,
        PerformanceReview $review
    ): PerformanceReview {
        if ($review->status !== 'submitted') {
            throw new InvalidArgumentException('Hanya performance review yang sudah submitted yang dapat ditolak.');
        }

        $this->authorizeUserAccess($user, $review);

        $review->status = 'rejected';
        $review->save();

        return $review->refresh()->load([
            'employee',
            'period',
            'reviewer',
            'items.indicator',
        ]);
    }

    private function authorizeUserAccess(
        User $user,
        PerformanceReview $review
    ): void {
        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            return;
        }

        if ($user->hasRole('manager')) {
            if (
                $review->employee?->manager?->user_id !== $user->id
            ) {
                throw new InvalidArgumentException('Manager hanya dapat mengakses performance review direct report.');
            }

            return;
        }

        if ($user->hasRole('employee')) {
            if (
                $review->employee?->user_id !== $user->id
            ) {
                throw new InvalidArgumentException('Employee hanya dapat mengakses performance review miliknya sendiri.');
            }

            return;
        }

        throw new InvalidArgumentException('User tidak memiliki akses ke performance review ini.');
    }
}
