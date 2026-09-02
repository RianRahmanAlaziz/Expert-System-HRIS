<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'employee_number' => $this->employee_number,
            'employee_name' => $this->employee_name,

            'leave_type_id' => $this->leave_type_id,
            'leave_type' => $this->leave_type,

            'year' => $this->year,

            'allocated_days' => $this->allocated_days,
            'used_days' => $this->used_days,
            'remaining_days' => $this->remaining_days,

            'total_requests' => $this->total_requests,
            'pending_requests' => $this->pending_requests,
            'approved_requests' => $this->approved_requests,
            'rejected_requests' => $this->rejected_requests,
            'cancelled_requests' => $this->cancelled_requests,
        ];
    }
}
