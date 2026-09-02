<?php

namespace App\Http\Resources\V1\Performance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReviewItemResource extends JsonResource
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
            'performance_review_id' => $this->performance_review_id,
            'performance_indicator_id' => $this->performance_indicator_id,
            'score' => $this->score,
            'comment' => $this->comment,

            'indicator' => new PerformanceIndicatorResource(
                $this->whenLoaded('indicator')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
