<?php

namespace App\Http\Requests\Competency;


use Illuminate\Foundation\Http\FormRequest;

class StoreCompetencyRequest extends FormRequest
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
                'required',
                'string',
                'max:50',
                'unique:competencies,code',
            ],
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'category' => [
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'required',
                'string',
                'max:30',
            ],
        ];
    }
}
