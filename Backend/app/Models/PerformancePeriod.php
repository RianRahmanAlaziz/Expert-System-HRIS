<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'start_date',
    'end_date',
    'status',
    'description',
])]
class PerformancePeriod extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }
}
