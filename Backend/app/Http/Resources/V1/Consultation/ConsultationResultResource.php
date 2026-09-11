<?php

namespace App\Http\Resources\V1\Consultation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResultResource extends JsonResource
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
            'expert_consultation_id' => $this->expert_consultation_id,
            'recommendation' => $this->recommendation,
            'score' => $this->score,
            'confidence' => $this->confidence,
            'reason' => $this->reason,
            'input_snapshot' => $this->input_snapshot,
            'matched_rules' => $this->matched_rules,
            'suggested_actions' => $this->suggested_actions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
