<?php

namespace App\Http\Requests\Promotion;


use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionAssessmentItemRequest extends FormRequest
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
            'promotion_assessment_id' => [
                'sometimes',
                'integer',
                'exists:promotion_assessments,id',
            ],

            'criterion_type' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'criterion_code' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'criterion_name' => [
                'sometimes',
                'string',
                'max:150',
            ],

            'score' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'weight' => [
                'sometimes',
                'numeric',
                'between:0,100',
            ],

            'is_passed' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}
