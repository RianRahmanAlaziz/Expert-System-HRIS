<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmploymentHistoryResource extends JsonResource
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

            'employment_type' => $this->employment_type,

            'start_date' => $this->start_date,
            'end_date' => $this->end_date,

            'reason' => $this->reason,
            'notes' => $this->notes,

            'department' => $this->whenLoaded('department', function () {
                return $this->department ? [
                    'id' => $this->department->id,
                    'code' => $this->department->code,
                    'name' => $this->department->name,
                ] : null;
            }),

            'position' => $this->whenLoaded('position', function () {
                return $this->position ? [
                    'id' => $this->position->id,
                    'code' => $this->position->code,
                    'name' => $this->position->name,
                    'level' => $this->position->level,
                ] : null;
            }),

            'manager' => $this->whenLoaded('manager', function () {
                return $this->manager ? [
                    'id' => $this->manager->id,
                    'employee_number' => $this->manager->employee_number,
                    'full_name' => trim(
                        "{$this->manager->first_name} {$this->manager->last_name}"
                    ),
                ] : null;
            }),
        ];
    }
}
