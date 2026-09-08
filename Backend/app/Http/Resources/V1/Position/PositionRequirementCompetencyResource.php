<?php

namespace App\Http\Resources\V1\Position;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionRequirementCompetencyResource extends JsonResource
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
            'position_requirement_id' =>  $this->position_requirement_id,
            'competency_id' =>  $this->competency_id,
            'competency' => $this->whenLoaded(
                'competency',
                fn() => [
                    'id' => $this->competency->id,
                    'code' => $this->competency->code,
                    'name' => $this->competency->name,
                    'category' => $this->competency->category,
                ]
            ),

            'required_level_id' =>  $this->required_level_id,
            'required_level' => $this->whenLoaded(
                'requiredLevel',
                fn() => [
                    'id' => $this->requiredLevel->id,
                    'level' => $this->requiredLevel->level,
                    'name' => $this->requiredLevel->name,
                ]
            ),
            'minimum_score' =>  $this->minimum_score,
            'weight' => $this->weight,
            'is_required' => $this->is_required,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
