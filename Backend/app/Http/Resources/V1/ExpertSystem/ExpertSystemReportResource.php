<?php

namespace App\Http\Resources\V1\ExpertSystem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpertSystemReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'],
            'by_recommendation' => $this->resource['by_recommendation'],
            'by_consultation_type' => $this->resource['by_consultation_type'],
            'consultations' => $this->resource['consultations'],
        ];
    }
}
