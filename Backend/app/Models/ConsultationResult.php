<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'expert_consultation_id',
    'recommendation',
    'score',
    'confidence',
    'reason',
    'input_snapshot',
    'matched_rules',
    'suggested_actions',
])]
class ConsultationResult extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'confidence' => 'decimal:2',
            'input_snapshot' => 'array',
            'matched_rules' => 'array',
            'suggested_actions' => 'array',
        ];
    }

    public function expertConsultation(): BelongsTo
    {
        return $this->belongsTo(ExpertConsultation::class);
    }
}
