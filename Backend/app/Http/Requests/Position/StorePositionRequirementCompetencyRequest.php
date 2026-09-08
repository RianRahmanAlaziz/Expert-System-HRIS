<?php

namespace App\Http\Requests\Position;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequirementCompetencyRequest extends FormRequest
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
            'position_requirement_id' => [
                'required',
                'integer',
                'exists:position_requirements,id',
            ],

            'competency_id' => [
                'required',
                'integer',
                'exists:competencies,id',
            ],

            'required_level_id' => [
                'nullable',
                'integer',
                'exists:competency_levels,id',
            ],

            'minimum_score' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'weight' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'is_required' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
