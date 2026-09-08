<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'description', 'level', 'status', 'is_active'])]
class Position extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function positionRequirements(): HasMany
    {
        return $this->hasMany(PositionRequirement::class);
    }
    public function careerPathPositions(): HasMany
    {
        return $this->hasMany(CareerPathPosition::class);
    }

    public function currentPromotionAssessments(): HasMany
    {
        return $this->hasMany(PromotionAssessment::class,   'current_position_id',);
    }

    public function targetPromotionAssessments(): HasMany
    {
        return $this->hasMany(PromotionAssessment::class,    'target_position_id',);
    }
}
