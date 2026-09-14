<?php

namespace App\Http\Resources\V1\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->resource['employee_id'],
            'employee_number' => $this->resource['employee_number'],
            'employee_name' => $this->resource['employee_name'],
            'present' => $this->resource['present'],
            'late' => $this->resource['late'],
            'absent' => $this->resource['absent'],
            'total_late_minutes' => $this->resource['total_late_minutes'],
            'total_working_minutes' => $this->resource['total_working_minutes'],
        ];
    }
}
