<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'current_position_id',
    'target_position_id',
    'assessed_by',
    'assessment_date',
    'status',
    'overall_score',
    'recommendation',
    'notes',
])]
class PromotionAssessment extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currentPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'current_position_id');
    }

    public function targetPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'target_position_id');
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromotionAssessmentItem::class);
    }

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'current_position_id' => 'integer',
            'target_position_id' => 'integer',
            'assessed_by' => 'integer',
            'assessment_date' => 'date',
            'overall_score' => 'decimal:2',
        ];
    }
}
