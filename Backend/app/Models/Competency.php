<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'category',
    'description',
    'status',
])]
class Competency extends Model
{
    public function employeeCompetencies(): HasMany
    {
        return $this->hasMany(EmployeeCompetency::class);
    }
}
