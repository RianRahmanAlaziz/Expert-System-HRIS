<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'performance_review_id',
    'performance_indicator_id',
    'score',
    'comment',
])]

class PerformanceReviewItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'performance_review_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicator::class, 'performance_indicator_id');
    }
}
