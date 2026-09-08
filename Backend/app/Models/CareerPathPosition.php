<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'career_path_id',
    'position_id',
    'sequence',
    'is_entry',
    'is_target',
])]
class CareerPathPosition extends Model
{
    public function careerPath(): BelongsTo
    {
        return $this->belongsTo(CareerPath::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    protected function casts(): array
    {
        return [
            'career_path_id' => 'integer',
            'position_id' => 'integer',
            'sequence' => 'integer',
            'is_entry' => 'boolean',
            'is_target' => 'boolean',
        ];
    }
}
