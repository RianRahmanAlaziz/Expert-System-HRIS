<?php

namespace App\Http\Requests\Promotion;


use Illuminate\Foundation\Http\FormRequest;

class StorePromotionAssessmentItemRequest extends FormRequest
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
                'required',
                'integer',
                'exists:promotion_assessments,id',
            ],

            'criterion_type' => [
                'required',
                'string',
                'max:50',
            ],

            'criterion_code' => [
                'required',
                'string',
                'max:100',
            ],

            'criterion_name' => [
                'required',
                'string',
                'max:150',
            ],

            'score' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],

            'weight' => [
                'required',
                'numeric',
                'between:0,100',
            ],

            'is_passed' => [
                'sometimes',
                'nullable',
                'boolean',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
