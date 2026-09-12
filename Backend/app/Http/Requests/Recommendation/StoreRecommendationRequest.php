<?php

namespace App\Http\Requests\Recommendation;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecommendationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'expert_consultation_id' => [
                'required',
                'integer',
                'exists:expert_consultations,id',
            ],

            'type' => [
                'required',
                'string',
                'in:promotion,training,career,performance_improvement,employee_risk',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'priority' => [
                'sometimes',
                'string',
                'max:30',
            ],
        ];
    }
}
