<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'expert_rule_id',
    'action_type',
    'action_value',
    'description',
])]
class RuleAction extends Model
{
    public function expertRule(): BelongsTo
    {
        return $this->belongsTo(ExpertRule::class);
    }
}
