<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'gender' => $this->gender,
            'birth_date' => $this->birth_date,
            'phone' => $this->phone,
            'address' => $this->address,
            'join_date' => $this->join_date,
            'employment_type' => $this->employment_type,
            'employment_status' => $this->employment_status,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'email' => $this->user?->email,
                    'is_active' => $this->user?->is_active,
                ];
            }),

            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department?->id,
                    'code' => $this->department?->code,
                    'name' => $this->department?->name,
                ];
            }),

            'position' => $this->whenLoaded('position', function () {
                return [
                    'id' => $this->position?->id,
                    'code' => $this->position?->code,
                    'name' => $this->position?->name,
                    'level' => $this->position?->level,
                ];
            }),

            'manager' => $this->whenLoaded('manager', function () {
                return $this->manager ? [
                    'id' => $this->manager->id,
                    'employee_number' => $this->manager->employee_number,
                    'full_name' => trim(
                        "{$this->manager->first_name} {$this->manager->last_name}"
                    ),
                ] : null;
            }),

            'subordinates' => $this->whenLoaded('subordinates', function () {
                return $this->subordinates->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'employee_number' => $employee->employee_number,
                        'full_name' => trim(
                            "{$employee->first_name} {$employee->last_name}"
                        ),
                    ];
                });
            }),

            'employment_histories' => EmploymentHistoryResource::collection(
                $this->whenLoaded('employmentHistories')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
