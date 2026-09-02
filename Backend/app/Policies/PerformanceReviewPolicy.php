<?php

namespace App\Policies;

use App\Models\PerformanceReview;
use App\Models\User;

class PerformanceReviewPolicy
{
    /**
     * Determine whether the user can view any performance reviews.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the performance review.
     */
    public function view(
        User $user,
        PerformanceReview $review
    ): bool {
        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $this->isManagerOfEmployee(
                $user,
                $review
            );
        }

        if ($user->hasRole('employee')) {
            return $this->isOwnReview(
                $user,
                $review
            );
        }

        return false;
    }

    /**
     * Determine whether the user can create a performance review.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the performance review.
     */
    public function update(
        User $user,
        PerformanceReview $review
    ): bool {
        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $this->isManagerOfEmployee(
                $user,
                $review
            );
        }

        if ($user->hasRole('employee')) {
            return $this->isOwnReview(
                $user,
                $review
            );
        }

        return false;
    }

    /**
     * Determine whether the user can submit the performance review.
     */
    public function submit(
        User $user,
        PerformanceReview $review
    ): bool {
        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $this->isManagerOfEmployee(
                $user,
                $review
            );
        }

        if ($user->hasRole('employee')) {
            return $this->isOwnReview(
                $user,
                $review
            );
        }

        return false;
    }

    /**
     * Determine whether the user can approve the performance review.
     */
    public function approve(
        User $user,
        PerformanceReview $review
    ): bool {
        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $this->isManagerOfEmployee(
                $user,
                $review
            );
        }

        return false;
    }

    /**
     * Determine whether the user can reject the performance review.
     */
    public function reject(
        User $user,
        PerformanceReview $review
    ): bool {
        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $this->isManagerOfEmployee(
                $user,
                $review
            );
        }

        return false;
    }

    /**
     * Determine whether the review belongs to the authenticated user.
     */
    private function isOwnReview(
        User $user,
        PerformanceReview $review
    ): bool {
        return $review->employee?->user_id === $user->id;
    }

    /**
     * Determine whether the authenticated user is the employee's manager.
     */
    private function isManagerOfEmployee(
        User $user,
        PerformanceReview $review
    ): bool {
        $employee = $review->employee;
        if (!$employee) {
            return false;
        }
        return $employee->manager?->user_id === $user->id;
    }

    public function delete(
        User $user,
        PerformanceReview $review
    ): bool {
        if ($user->hasAnyRole([
            'super-admin',
            'admin',
            'hr-admin',
        ])) {
            return true;
        }

        if ($user->hasRole('manager')) {
            return $this->isManagerOfEmployee($user, $review);
        }

        if ($user->hasRole('employee')) {
            return $this->isOwnReview($user, $review);
        }

        return false;
    }
}
