<?php

namespace App\Http\Resources\V1\Performance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this['summary'],
            'by_department' => $this['by_department'],
            'by_review_type' => $this['by_review_type'],
            'by_period' => $this['by_period'],
        ];
    }
}
