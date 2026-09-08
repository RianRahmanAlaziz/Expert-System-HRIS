<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promotion_assessment_id',
    'criterion_type',
    'criterion_code',
    'criterion_name',
    'score',
    'weight',
    'is_passed',
    'notes',
])]

class PromotionAssessmentItem extends Model
{
    public function promotionAssessment(): BelongsTo
    {
        return $this->belongsTo(PromotionAssessment::class);
    }

    protected function casts(): array
    {
        return [
            'promotion_assessment_id' => 'integer',
            'score' => 'decimal:2',
            'weight' => 'decimal:2',
            'is_passed' => 'boolean',
        ];
    }
}
