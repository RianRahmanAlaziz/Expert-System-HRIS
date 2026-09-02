<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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

            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_days' => $this->total_days,

            'reason' => $this->reason,
            'status' => $this->status,

            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'rejection_reason' => $this->rejection_reason,

            'employee' => $this->whenLoaded(
                'employee',
                fn() => [
                    'id' => $this->employee->id,
                    'employee_number' => $this->employee->employee_number,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                ]
            ),

            'leave_type' => $this->whenLoaded(
                'leaveType',
                fn() => [
                    'id' => $this->leaveType->id,
                    'name' => $this->leaveType->name,
                    'code' => $this->leaveType->code,
                ]
            ),

            'approved_by_user' => $this->whenLoaded(
                'approvedBy',
                fn() => [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                    'email' => $this->approvedBy->email,
                ]
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
