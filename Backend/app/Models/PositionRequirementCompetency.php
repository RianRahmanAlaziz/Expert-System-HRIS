<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'position_requirement_id',
    'competency_id',
    'required_level_id',
    'minimum_score',
    'weight',
    'is_required',
])]
class PositionRequirementCompetency extends Model
{
    public function positionRequirement(): BelongsTo
    {
        return $this->belongsTo(PositionRequirement::class);
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function requiredLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class,     'required_level_id');
    }

    protected function casts(): array
    {
        return [
            'position_requirement_id' => 'integer',
            'competency_id' => 'integer',
            'required_level_id' => 'integer',
            'minimum_score' => 'decimal:2',
            'weight' => 'decimal:2',
            'is_required' => 'boolean',
        ];
    }
}
