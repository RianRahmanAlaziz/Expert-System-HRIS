<?php

namespace App\Http\Resources\V1\Performance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceIndicatorResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'target' => $this->target,
            'weight' => $this->weight,
            'measurement_type' => $this->measurement_type,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
