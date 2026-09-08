<?php

namespace App\Http\Resources\V1\Promotion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionAssessmentResource extends JsonResource
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
            'current_position_id' => $this->current_position_id,
            'target_position_id' => $this->target_position_id,
            'assessed_by' => $this->assessed_by,

            'assessment_date' => $this->assessment_date?->format('Y-m-d'),
            'status' => $this->status,
            'overall_score' => $this->overall_score,
            'recommendation' => $this->recommendation,
            'notes' => $this->notes,

            'employee' => $this->whenLoaded(
                'employee',
                fn() => [
                    'id' => $this->employee->id,
                    'employee_number' => $this->employee->employee_number,
                    'name' => trim(
                        $this->employee->first_name . ' ' .
                            $this->employee->last_name
                    ),
                ],
            ),

            'current_position' => $this->whenLoaded(
                'currentPosition',
                fn() => [
                    'id' => $this->currentPosition->id,
                    'code' => $this->currentPosition->code,
                    'name' => $this->currentPosition->name,
                ],
            ),

            'target_position' => $this->whenLoaded(
                'targetPosition',
                fn() => [
                    'id' => $this->targetPosition->id,
                    'code' => $this->targetPosition->code,
                    'name' => $this->targetPosition->name,
                ],
            ),

            'assessed_by_user' => $this->whenLoaded(
                'assessedBy',
                fn() => [
                    'id' => $this->assessedBy->id,
                    'name' => $this->assessedBy->name,
                    'email' => $this->assessedBy->email,
                ],
            ),

            'items' => PromotionAssessmentItemResource::collection(
                $this->whenLoaded('items'),
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
