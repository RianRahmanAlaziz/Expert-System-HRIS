<?php

namespace App\Http\Resources\V1\Career;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareerPathPositionResource extends JsonResource
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
            'career_path_id' => $this->career_path_id,
            'position_id' => $this->position_id,
            'position' => $this->whenLoaded(
                'position',
                fn() => [
                    'id' => $this->position->id,
                    'code' => $this->position->code,
                    'name' => $this->position->name,
                ],
            ),
            'sequence' => $this->sequence,
            'is_entry' => $this->is_entry,
            'is_target' => $this->is_target,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
