<?php

namespace App\Http\Resources\V1\ExpertSystem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KnowledgeResource extends JsonResource
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
            'knowledge_category' => $this->whenLoaded(
                'knowledgeCategory',
                fn() => [
                    'id' => $this->knowledgeCategory->id,
                    'name' => $this->knowledgeCategory->name,
                    'code' => $this->knowledgeCategory->code,
                ]
            ),
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
