<?php

namespace App\Http\Resources\V1\Performance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReviewResource extends JsonResource
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
            'performance_period_id' => $this->performance_period_id,
            'reviewer_id' => $this->reviewer_id,

            'review_type' => $this->review_type,
            'status' => $this->status,
            'overall_score' => $this->overall_score,
            'review_date' => $this->review_date?->format('Y-m-d'),
            'comments' => $this->comments,

            'employee' => $this->whenLoaded(
                'employee',
                fn() => [
                    'id' => $this->employee->id,
                    'employee_number' => $this->employee->employee_number,
                    'name' => trim(
                        $this->employee->first_name . ' ' .
                            $this->employee->last_name
                    ),
                ]
            ),

            'period' => new PerformancePeriodResource(
                $this->whenLoaded('period')
            ),

            'reviewer' => $this->whenLoaded(
                'reviewer',
                fn() => [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                    'email' => $this->reviewer->email,
                ]
            ),

            'items' => PerformanceReviewItemResource::collection(
                $this->whenLoaded('items')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
