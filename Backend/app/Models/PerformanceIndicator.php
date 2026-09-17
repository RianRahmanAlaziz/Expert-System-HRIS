<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'description',
    'weight',
    'target',
    'unit',
    'status',
])]
class PerformanceIndicator extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'target' => 'decimal:2',
        ];
    }

    public function reviewItems(): HasMany
    {
        return $this->hasMany(PerformanceReviewItem::class);
    }
}
