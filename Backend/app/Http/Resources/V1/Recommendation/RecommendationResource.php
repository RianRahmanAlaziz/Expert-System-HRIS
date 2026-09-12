<?php

namespace App\Http\Resources\V1\Recommendation;

use App\Http\Resources\V1\EmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationResource extends JsonResource
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
            'employee' => EmployeeResource::make(
                $this->whenLoaded('employee'),
            ),
            'expert_consultation_id' => $this->expert_consultation_id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'recommended_at' => $this->recommended_at,
            'histories' => RecommendationHistoryResource::collection(
                $this->whenLoaded('histories'),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
