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

    public function messages(): array
    {
        return [
            'code.string' => 'Kode position harus berupa teks.',
            'code.max' => 'Kode position maksimal 50 karakter.',
            'code.unique' => 'Kode position sudah digunakan.',

            'name.string' => 'Nama position harus berupa teks.',
            'name.max' => 'Nama position maksimal 150 karakter.',

            'description.string' => 'Deskripsi position harus berupa teks.',

            'level.integer' => 'Level position harus berupa angka.',

            'status.string' => 'Status position harus berupa teks.',
            'status.max' => 'Status position maksimal 30 karakter.',

            'is_active.boolean' => 'Status aktif harus berupa boolean.',
        ];
    }
}
