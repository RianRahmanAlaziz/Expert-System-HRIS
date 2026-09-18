<?php

namespace App\Http\Resources\V1\Consultation;

use App\Http\Resources\V1\Employee\EmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpertConsultationResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee' => EmployeeResource::make(
                $this->whenLoaded('employee')
            ),

            'user_id' => $this->user_id,
            'consultation_type' => $this->consultation_type,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'result' => ConsultationResultResource::make(
                $this->whenLoaded('result'),
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
