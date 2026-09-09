<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'knowledge_id',
    'code',
    'name',
    'description',
    'priority',
    'status',
])]
class ExpertRule extends Model
{

    protected $casts = [
        'priority' => 'integer',
    ];

    public function knowledge(): BelongsTo
    {
        return $this->belongsTo(Knowledge::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(RuleCondition::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RuleAction::class);
    }
}
