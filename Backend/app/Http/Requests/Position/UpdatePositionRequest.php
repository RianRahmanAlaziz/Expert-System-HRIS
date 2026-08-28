<?php

namespace App\Http\Requests\Position;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
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
                Rule::unique('positions', 'code')->ignore($this->position),
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
            'level' => [
                'sometimes',
                'integer',
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
