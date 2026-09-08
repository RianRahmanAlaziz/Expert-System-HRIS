<?php

namespace App\Http\Resources\V1\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionAssessmentItemResource extends JsonResource
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
            'promotion_assessment_id' =>   $this->promotion_assessment_id,
            'criterion_type' => $this->criterion_type,
            'criterion_code' => $this->criterion_code,
            'criterion_name' => $this->criterion_name,
            'score' => $this->score,
            'weight' => $this->weight,
            'is_passed' => $this->is_passed,
            'notes' => $this->notes,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
