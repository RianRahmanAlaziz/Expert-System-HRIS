<?php

namespace App\Http\Requests\Position;


use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
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
                'unique:positions,code',
            ],
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'level' => [
                'required',
                'integer',
            ],
            'status' => [
                'required',
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
