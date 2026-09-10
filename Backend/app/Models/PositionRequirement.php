<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'position_id',
    'minimum_experience_years',
    'minimum_performance_score',
    'minimum_attendance_percentage',
    'description',
    'status',
    'is_active',
])]
class PositionRequirement extends Model
{
    use SoftDeletes;

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function positionRequirementCompetencies(): HasMany
    {
        return $this->hasMany(
            PositionRequirementCompetency::class
        );
    }

    protected function casts(): array
    {
        return [
            'position_id' => 'integer',
            'minimum_experience_years' => 'decimal:2',
            'minimum_performance_score' => 'decimal:2',
            'minimum_attendance_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
