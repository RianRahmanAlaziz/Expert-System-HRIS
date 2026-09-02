<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'performance_period_id',
    'reviewer_id',
    'review_type',
    'status',
    'overall_score',
    'review_date',
    'comments',
])]
class PerformanceReview extends Model
{
    use HasFactory;
    protected function casts(): array
    {
        return [
            'overall_score' => 'decimal:2',
            'review_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(
            PerformancePeriod::class,
            'performance_period_id'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PerformanceReviewItem::class);
    }
}
