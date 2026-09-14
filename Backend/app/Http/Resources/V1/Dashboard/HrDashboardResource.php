<?php

namespace App\Http\Resources\V1\Dashboard;

use App\Http\Resources\V1\Consultation\ExpertConsultationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee' => $this->resource['employee'],
            'attendance' => $this->resource['attendance'],
            'leave' => $this->resource['leave'],
            'performance' => $this->resource['performance'],
            'competency' => $this->resource['competency'],
            'employee_turnover' => $this->resource['employee_turnover'],

            'expert_system' => [
                'total_consultations' => $this->resource['expert_system']['total_consultations'],
                'recommendation_distribution' => $this->resource['expert_system']['recommendation_distribution'],
                'high_risk_employee' => $this->resource['expert_system']['high_risk_employee'],
                'promotion_candidate' => $this->resource['expert_system']['promotion_candidate'],
                'training_candidate' => $this->resource['expert_system']['training_candidate'],
                'competency_gap' => $this->resource['expert_system']['competency_gap'],
                'recent_consultations' => ExpertConsultationResource::collection(
                    $this->resource['expert_system']['recent_consultations'],
                ),
            ],

            'recommendation' => $this->resource['recommendation'],
        ];
    }
}
