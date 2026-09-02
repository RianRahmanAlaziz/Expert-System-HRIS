<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'category',
    'target',
    'weight',
    'measurement_type',
    'is_active',
])]
class PerformanceIndicator extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'target' => 'decimal:2',
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function reviewItems(): HasMany
    {
        return $this->hasMany(PerformanceReviewItem::class);
    }
}
