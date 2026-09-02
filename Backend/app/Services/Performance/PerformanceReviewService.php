<?php

namespace App\Services\Performance;

use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PerformanceReviewService
{
    public function __construct(
        protected PerformanceScoreService $scoreService
    ) {}

    public function getAll(User $user): Collection
    {
        $query = PerformanceReview::query()
            ->with([
                'employee',
                'period',
                'reviewer',
            ])
            ->latest();

        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            return $query->get();
        }

        if ($user->hasRole('manager')) {
            return $query
                ->whereHas('employee.manager', function ($managerQuery) use ($user) {
                    $managerQuery->where('user_id', $user->id);
                })
                ->get();
        }

        if ($user->hasRole('employee')) {
            return $query
                ->whereHas('employee', function ($employeeQuery) use ($user) {
                    $employeeQuery->where('user_id', $user->id);
                })
                ->get();
        }

        return new Collection();
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
