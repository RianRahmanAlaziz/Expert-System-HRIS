<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'expert_rule_id',
    'parameter',
    'operator',
    'value',
    'logical_operator',
    'sort_order',
])]
class RuleCondition extends Model
{
    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function expertRule(): BelongsTo
    {
        return $this->belongsTo(ExpertRule::class);
    }
}
