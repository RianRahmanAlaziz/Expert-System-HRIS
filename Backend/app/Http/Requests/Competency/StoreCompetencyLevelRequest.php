<?php

namespace App\Http\Requests\Competency;


use Illuminate\Foundation\Http\FormRequest;

class StoreCompetencyLevelRequest extends FormRequest
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
                'required',
                'integer',
                'unique:competency_levels,level',
            ],
            'name' => [
                'required',
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
