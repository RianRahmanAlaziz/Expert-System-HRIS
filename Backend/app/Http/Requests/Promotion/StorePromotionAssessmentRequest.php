<?php

namespace App\Http\Requests\Promotion;


use Illuminate\Foundation\Http\FormRequest;

class StorePromotionAssessmentRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
            ],

            'current_position_id' => [
                'required',
                'integer',
                'exists:positions,id',
            ],

            'target_position_id' => [
                'required',
                'integer',
                'exists:positions,id',
            ],

            'assessment_date' => [
                'required',
                'date',
            ],

            'status' => [
                'required',
                'string',
                'max:30',
            ],

            'overall_score' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'recommendation' => [
                'nullable',
                'string',
                'max:30',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
