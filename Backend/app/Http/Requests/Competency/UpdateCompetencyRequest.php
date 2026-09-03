<?php

namespace App\Http\Requests\Competency;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompetencyRequest extends FormRequest
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
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('competencies', 'code')->ignore($this->route('competency')),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:150',
            ],
            'category' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'sometimes',
                'string',
                'max:30',
            ],
        ];
    }
}
