<?php

namespace App\Services\Promotion;

use App\Models\PromotionAssessmentItem;
use App\Services\SystemSupport\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionAssessmentItemService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function paginate(
        int $perPage = 15,
        ?int $promotionAssessmentId = null,
        ?string $criterionType = null,
        ?bool $isPassed = null,
    ): LengthAwarePaginator {
        return PromotionAssessmentItem::query()
            ->with('promotionAssessment')
            ->when(
                $promotionAssessmentId !== null,
                fn($query) => $query->where(
                    'promotion_assessment_id',
                    $promotionAssessmentId,
                ),
            )
            ->when(
                $criterionType !== null,
                fn($query) => $query->where(
                    'criterion_type',
                    $criterionType,
                ),
            )
            ->when(
                $isPassed !== null,
                fn($query) => $query->where(
                    'is_passed',
                    $isPassed,
                ),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): PromotionAssessmentItem
    {
        return PromotionAssessmentItem::query()
            ->with('promotionAssessment')
            ->findOrFail($id);
    }

    public function findByPromotionAssessmentId(
        int $promotionAssessmentId,
    ): Collection {
        return PromotionAssessmentItem::query()
            ->where('promotion_assessment_id', $promotionAssessmentId)
            ->orderBy('criterion_type')
            ->orderBy('criterion_code')
            ->get();
    }

    public function create(array $data): PromotionAssessmentItem
    {
        return DB::transaction(
            function () use ($data): PromotionAssessmentItem {
                $this->ensureUniqueCriterion(
                    promotionAssessmentId: $data['promotion_assessment_id'],
                    criterionCode: $data['criterion_code'],
                );

                $item = PromotionAssessmentItem::query()->create([
                    'promotion_assessment_id' =>
                    $data['promotion_assessment_id'],
                    'criterion_type' => $data['criterion_type'],
                    'criterion_code' => $data['criterion_code'],
                    'criterion_name' => $data['criterion_name'],
                    'score' => $data['score'] ?? null,
                    'weight' => $data['weight'],
                    'is_passed' => $data['is_passed'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                $this->activityLogService->log(
                    action: 'create',
                    module: 'promotion_assessment_item',
                    target: $item,
                    newValues: [
                        'promotion_assessment_id' => $item->promotion_assessment_id,
                        'criterion_type' => $item->criterion_type,
                        'criterion_code' => $item->criterion_code,
                        'criterion_name' => $item->criterion_name,
                        'score' => $item->score,
                        'weight' => $item->weight,
                        'is_passed' => $item->is_passed,
                        'notes' => $item->notes,
                    ],
                );

                return $item->load('promotionAssessment');
            },
        );
    }

    public function update(
        PromotionAssessmentItem $promotionAssessmentItem,
        array $data,
    ): PromotionAssessmentItem {
        return DB::transaction(
            function () use (
                $promotionAssessmentItem,
                $data,
            ): PromotionAssessmentItem {
                $promotionAssessmentId = $data['promotion_assessment_id']  ?? $promotionAssessmentItem->promotion_assessment_id;

                $criterionCode =  $data['criterion_code']   ?? $promotionAssessmentItem->criterion_code;

                $this->ensureUniqueCriterion(
                    promotionAssessmentId: $promotionAssessmentId,
                    criterionCode: $criterionCode,
                    ignoreId: $promotionAssessmentItem->id,
                );

                $oldValues = [
                    'promotion_assessment_id' =>  $promotionAssessmentItem->promotion_assessment_id,
                    'criterion_type' =>  $promotionAssessmentItem->criterion_type,
                    'criterion_code' =>  $promotionAssessmentItem->criterion_code,
                    'criterion_name' => $promotionAssessmentItem->criterion_name,
                    'score' =>  $promotionAssessmentItem->score,
                    'weight' => $promotionAssessmentItem->weight,
                    'is_passed' => $promotionAssessmentItem->is_passed,
                    'notes' => $promotionAssessmentItem->notes,
                ];

                $promotionAssessmentItem->update($data);

                $promotionAssessmentItem->refresh();

                $this->activityLogService->log(
                    action: 'update',
                    module: 'promotion_assessment_item',
                    target: $promotionAssessmentItem,
                    oldValues: $oldValues,
                    newValues: [
                        'promotion_assessment_id' =>  $promotionAssessmentItem->promotion_assessment_id,
                        'criterion_type' =>  $promotionAssessmentItem->criterion_type,
                        'criterion_code' => $promotionAssessmentItem->criterion_code,
                        'criterion_name' =>  $promotionAssessmentItem->criterion_name,
                        'score' => $promotionAssessmentItem->score,
                        'weight' => $promotionAssessmentItem->weight,
                        'is_passed' => $promotionAssessmentItem->is_passed,
                        'notes' => $promotionAssessmentItem->notes,
                    ],
                );

                return $promotionAssessmentItem->load(
                    'promotionAssessment',
                );
            },
        );
    }

    public function delete(
        PromotionAssessmentItem $promotionAssessmentItem,
    ): void {
        DB::transaction(
            function () use ($promotionAssessmentItem): void {
                $oldValues = [
                    'promotion_assessment_id' =>  $promotionAssessmentItem->promotion_assessment_id,
                    'criterion_type' => $promotionAssessmentItem->criterion_type,
                    'criterion_code' => $promotionAssessmentItem->criterion_code,
                    'criterion_name' => $promotionAssessmentItem->criterion_name,
                    'score' =>  $promotionAssessmentItem->score,
                    'weight' => $promotionAssessmentItem->weight,
                    'is_passed' =>  $promotionAssessmentItem->is_passed,
                    'notes' => $promotionAssessmentItem->notes,
                ];

                $promotionAssessmentItem->delete();

                $this->activityLogService->log(
                    action: 'delete',
                    module: 'promotion_assessment_item',
                    target: $promotionAssessmentItem,
                    oldValues: $oldValues,
                );
            },
        );
    }

    private function ensureUniqueCriterion(
        int $promotionAssessmentId,
        string $criterionCode,
        ?int $ignoreId = null,
    ): void {
        $exists = PromotionAssessmentItem::query()
            ->where(
                'promotion_assessment_id',
                $promotionAssessmentId,
            )
            ->where('criterion_code', $criterionCode)
            ->when(
                $ignoreId !== null,
                fn($query) => $query->where(
                    'id',
                    '!=',
                    $ignoreId,
                ),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'criterion_code' => [
                    'This criterion already exists in the selected promotion assessment.',
                ],
            ]);
        }
    }
}
