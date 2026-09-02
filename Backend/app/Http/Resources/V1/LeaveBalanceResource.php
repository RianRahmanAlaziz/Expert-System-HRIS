<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
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
            'leave_type_id' => $this->leave_type_id,
            'year' => $this->year,
            'allocated_days' => $this->allocated_days,
            'used_days' => $this->used_days,
            'remaining_days' => $this->remaining_days,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
