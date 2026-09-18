<?php

namespace App\Http\Requests\Career;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCareerPathRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'string',
                'max:100'
            ],
            'description' => [
                'nullable',
                'string'
            ],
            'status' => [
                'sometimes',
                'string',
                'max:30'
            ],
            'is_active' => [
                'sometimes',
                'boolean'
            ],
        ];
    }
}
