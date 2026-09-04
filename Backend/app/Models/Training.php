<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'category',
    'description',
    'trainer',
    'start_date',
    'end_date',
    'capacity',
    'status',
])]
class Training extends Model
{
    use HasFactory;

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }
}
