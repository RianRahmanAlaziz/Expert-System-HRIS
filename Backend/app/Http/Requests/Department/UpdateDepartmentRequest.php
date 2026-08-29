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

    public function messages(): array
    {
        return [
            'code.string' => 'Kode department harus berupa teks.',
            'code.max' => 'Kode department maksimal 50 karakter.',
            'code.unique' => 'Kode department sudah digunakan.',

            'name.string' => 'Nama department harus berupa teks.',
            'name.max' => 'Nama department maksimal 150 karakter.',

            'description.string' => 'Deskripsi department harus berupa teks.',

            'status.string' => 'Status department harus berupa teks.',
            'status.max' => 'Status department maksimal 30 karakter.',

            'is_active.boolean' => 'Status aktif harus berupa boolean.',
        ];
    }
}
