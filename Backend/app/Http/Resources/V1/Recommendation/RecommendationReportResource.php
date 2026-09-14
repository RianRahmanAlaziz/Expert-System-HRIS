<?php

namespace App\Http\Resources\V1\Recommendation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecommendationReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'],
            'by_type' => $this->resource['by_type'],
            'by_status' => $this->resource['by_status'],
            'by_priority' => $this->resource['by_priority'],
            'recommendations' => $this->resource['recommendations'],
        ];
    }
}
