<?php

namespace App\Http\Resources\V1\Performance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'employee' => [
                'id' => $this->employee?->id,
                'employee_number' => $this->employee?->employee_number,
                'first_name' => $this->employee?->first_name,
                'last_name' => $this->employee?->last_name,
            ],

            'period' => [
                'id' => $this->period?->id,
                'name' => $this->period?->name,
                'start_date' => $this->period?->start_date,
                'end_date' => $this->period?->end_date,
                'status' => $this->period?->status,
            ],

            'reviewer' => [
                'id' => $this->reviewer?->id,
            ],

            'review_type' => $this->review_type,
            'status' => $this->status,
            'overall_score' => $this->overall_score,
            'review_date' => $this->review_date,
            'comments' => $this->comments,
        ];
    }
}
