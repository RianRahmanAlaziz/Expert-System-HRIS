<?php

namespace App\Http\Resources\V1\ExpertSystem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RuleConditionResource extends JsonResource
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
            'expert_rule' => $this->whenLoaded(
                'expertRule',
                fn() => [
                    'id' => $this->expertRule->id,
                    'code' => $this->expertRule->code,
                    'name' => $this->expertRule->name,
                ]
            ),
            'parameter' => $this->parameter,
            'operator' => $this->operator,
            'value' => $this->value,
            'logical_operator' => $this->logical_operator,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
