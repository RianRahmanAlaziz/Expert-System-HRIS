<?php

namespace App\Http\Resources\V1\Training;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingParticipantResource extends JsonResource
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
            'training_id' => $this->training_id,
            'employee_id' => $this->employee_id,
            'training' => TrainingResource::make(
                $this->whenLoaded('training')
            ),
            'status' => $this->status,
            'score' => $this->score,
            'registered_at' => $this->registered_at,
            'completed_at' => $this->completed_at,
            'certificate_path' => $this->certificate_path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
