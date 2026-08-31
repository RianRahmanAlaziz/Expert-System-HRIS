<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'attendance_date' => $this->attendance_date?->format('Y-m-d'),
            'clock_in' => $this->clock_in?->format('Y-m-d H:i:s'),
            'clock_out' => $this->clock_out?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
            'working_minutes' => $this->working_minutes,
            'notes' => $this->notes,
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'employee_number' => $this->employee->employee_number,
                    'first_name' => $this->employee->first_name,
                    'last_name' => $this->employee->last_name,
                    'full_name' => trim(
                        $this->employee->first_name . ' ' . $this->employee->last_name
                    ),
                ];
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
