<?php

namespace App\Http\Resources\V1\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeReportResource extends JsonResource
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
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department?->id,
                    'code' => $this->department?->code,
                    'name' => $this->department?->name,
                ];
            }),
            'position' => $this->whenLoaded('position', function () {
                return [
                    'id' => $this->position?->id,
                    'code' => $this->position?->code,
                    'name' => $this->position?->name,
                    'level' => $this->position?->level,
                ];
            }),
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,
            'join_date' => $this->join_date,
        ];
    }
}
