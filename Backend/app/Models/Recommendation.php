<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'expert_consultation_id',
    'type',
    'title',
    'description',
    'priority',
    'status',
    'recommended_at',
])]

class Recommendation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'recommended_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function expertConsultation(): BelongsTo
    {
        return $this->belongsTo(ExpertConsultation::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RecommendationHistory::class);
    }
}
