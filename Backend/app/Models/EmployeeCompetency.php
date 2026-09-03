<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'competency_id',
    'competency_level_id',
    'score',
    'assessed_at',
    'assessed_by',
    'notes',
])]
class EmployeeCompetency extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function competencyLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
