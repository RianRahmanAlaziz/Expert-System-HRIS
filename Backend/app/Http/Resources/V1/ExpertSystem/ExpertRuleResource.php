<?php

namespace App\Http\Resources\V1\ExpertSystem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpertRuleResource extends JsonResource
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
            'knowledge' => $this->whenLoaded(
                'knowledge',
                fn() => [
                    'id' => $this->knowledge->id,
                    'name' => $this->knowledge->name,
                    'description' => $this->knowledge->description,
                    'version' => $this->knowledge->version,
                    'status' => $this->knowledge->status,
                ]
            ),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
