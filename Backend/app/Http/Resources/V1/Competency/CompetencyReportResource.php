<?php

namespace App\Http\Resources\V1\Competency;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetencyReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee' => [
                'id' => $this->resource['employee_id'],
                'employee_number' => $this->resource['employee_number'],
                'name' => $this->resource['employee_name'],
            ],

            'department' => [
                'id' => $this->resource['department_id'],
                'name' => $this->resource['department_name'],
            ],

            'position' => [
                'id' => $this->resource['position_id'],
                'name' => $this->resource['position_name'],
            ],

            'competency' => [
                'id' => $this->resource['competency_id'],
                'code' => $this->resource['competency_code'],
                'name' => $this->resource['competency_name'],
                'category' => $this->resource['category'],
            ],

            'current' => [
                'level' => $this->resource['current_level'],
                'level_name' => $this->resource['current_level_name'],
                'score' => $this->resource['current_score'],
            ],

            'requirement' => [
                'level' => $this->resource['required_level'],
                'level_name' => $this->resource['required_level_name'],
                'minimum_score' => $this->resource['minimum_score'],
                'is_required' => $this->resource['is_required'],
            ],

            'has_gap' => $this->resource['has_gap'],
        ];
    }
}
