<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'description',
    'status',
])]

class KnowledgeCategory extends Model
{
    public function knowledge(): HasMany
    {
        return $this->hasMany(Knowledge::class);
    }
}
