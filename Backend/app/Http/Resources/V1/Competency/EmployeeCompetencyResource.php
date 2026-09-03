<?php

namespace App\Http\Resources\V1\Competency;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeCompetencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'competency_id' => $this->competency_id,
            'competency_level_id' => $this->competency_level_id,
            'score' => $this->score,
            'assessed_at' => $this->assessed_at,
            'assessed_by' => $this->assessed_by,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
