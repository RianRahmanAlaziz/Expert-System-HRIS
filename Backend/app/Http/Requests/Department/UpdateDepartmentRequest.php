<?php

namespace App\Http\Requests\Department;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
                Rule::unique('departments', 'code')->ignore($this->department),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:150',
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
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
