<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'knowledge_category_id',
    'name',
    'description',
    'version',
    'status',
])]
class Knowledge extends Model
{
    protected $casts = [
        'version' => 'integer',
    ];

    public function knowledgeCategory(): BelongsTo
    {
        return $this->belongsTo(KnowledgeCategory::class);
    }

    public function expertRules(): HasMany
    {
        return $this->hasMany(ExpertRule::class);
    }
}
