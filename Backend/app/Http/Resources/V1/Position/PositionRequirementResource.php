<?php

namespace App\Http\Resources\V1\Position;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionRequirementResource extends JsonResource
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
            'position_id' => $this->position_id,
            'position' => $this->whenLoaded(
                'position',
                fn() => [
                    'id' => $this->position->id,
                    'code' => $this->position->code,
                    'name' => $this->position->name,
                    'level' => $this->position->level,
                ],
            ),

            'minimum_experience_years' => $this->minimum_experience_years,
            'minimum_performance_score' => $this->minimum_performance_score,
            'minimum_attendance_percentage' => $this->minimum_attendance_percentage,
            'description' => $this->description,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
