<?php

namespace App\Http\Requests\Competency;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompetencyLevelRequest extends FormRequest
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
            'level' => [
                'sometimes',
                'integer',
                Rule::unique('competency_levels', 'level')->ignore($this->route('competencyLevel')),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];
    }
}
