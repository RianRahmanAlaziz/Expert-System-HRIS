<?php

namespace App\Services\Performance;

use App\Models\PerformanceIndicator;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewItem;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PerformanceReviewItemService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginateByReview(
        PerformanceReview $review,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $review->performanceReviewItems()
            ->with('indicator')
            ->latest('id')
            ->paginate($perPage);
    }

    public function getById(
        PerformanceReviewItem $item
    ): PerformanceReviewItem {
        return $item->load('indicator');
    }

    public function create(
        PerformanceReview $review,
        array $data
    ): PerformanceReviewItem {
        if ($review->status === 'approved') {
            throw new InvalidArgumentException('Performance review yang sudah approved tidak dapat diubah.');
        }

        $indicator = PerformanceIndicator::findOrFail(
            $data['performance_indicator_id']
        );

        if ($indicator->status !== 'active') {
            throw new InvalidArgumentException(
                'Performance indicator yang dipilih tidak aktif.'
            );
        }

        $exists = $review->performanceReviewItems()
            ->where(
                'performance_indicator_id',
                $indicator->id
            )
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Performance indicator tersebut sudah digunakan dalam review.');
        }

        return DB::transaction(function () use ($review, $data) {
            $item = $review->performanceReviewItems()->create($data);

            $this->activityLogService->log(
                action: 'create',
                module: 'performance_review_item',
                target: $item,
                newValues: [
                    'performance_review_id' => $item->performance_review_id,
                    'performance_indicator_id' => $item->performance_indicator_id,
                    'score' => $item->score,
                    'comments' => $item->comments,
                ],
            );

            return $item->load('indicator');
        });
    }

    public function update(
        PerformanceReviewItem $item,
        array $data
    ): PerformanceReviewItem {
        $item->loadMissing('review');

        if ($item->review->status === 'approved') {
            throw new InvalidArgumentException('Performance review yang sudah approved tidak dapat diubah.');
        }

        if (
            isset($data['performance_indicator_id']) &&
            $data['performance_indicator_id'] !==
            $item->performance_indicator_id
        ) {
            $indicator = PerformanceIndicator::findOrFail(
                $data['performance_indicator_id']
            );

            if ($indicator->status !== 'active') {
                throw new InvalidArgumentException(
                    'Performance indicator yang dipilih tidak aktif.'
                );
            }

            $exists = $item->review->performanceReviewItems()
                ->where(
                    'performance_indicator_id',
                    $indicator->id
                )
                ->where(
                    'id',
                    '!=',
                    $item->id
                )
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException(
                    'Performance indicator tersebut sudah digunakan dalam review.'
                );
            }
        }
        $oldValues = [
            'performance_review_id' => $item->performance_review_id,
            'performance_indicator_id' => $item->performance_indicator_id,
            'score' => $item->score,
            'comments' => $item->comments,
        ];

        $item->update($data);

        $item = $item->refresh();

        $this->activityLogService->log(
            action: 'update',
            module: 'performance_review_item',
            target: $item,
            oldValues: $oldValues,
            newValues: [
                'performance_review_id' => $item->performance_review_id,
                'performance_indicator_id' => $item->performance_indicator_id,
                'score' => $item->score,
                'comments' => $item->comments,
            ],
        );

        return $item->load('indicator');
    }

    public function delete(
        PerformanceReviewItem $item
    ): void {
        $item->loadMissing('review');

        if ($item->review->status === 'approved') {
            throw new InvalidArgumentException(
                'Performance review yang sudah approved tidak dapat diubah.'
            );
        }

        $this->activityLogService->log(
            action: 'delete',
            module: 'performance_review_item',
            target: $item,
            oldValues: [
                'performance_review_id' => $item->performance_review_id,
                'performance_indicator_id' => $item->performance_indicator_id,
                'score' => $item->score,
                'comments' => $item->comments,
            ],
        );

        $item->delete();
    }
}
