<?php

namespace App\Http\Requests\Department;


use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
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
                'unique:departments,code',
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

    public function messages(): array
    {
        return [
            'code.required' => 'Kode department wajib diisi.',
            'code.string' => 'Kode department harus berupa teks.',
            'code.max' => 'Kode department maksimal 50 karakter.',
            'code.unique' => 'Kode department sudah digunakan.',

            'name.required' => 'Nama department wajib diisi.',
            'name.string' => 'Nama department harus berupa teks.',
            'name.max' => 'Nama department maksimal 150 karakter.',

            'description.string' => 'Deskripsi department harus berupa teks.',

            'status.required' => 'Status department wajib diisi.',
            'status.string' => 'Status department harus berupa teks.',
            'status.max' => 'Status department maksimal 30 karakter.',

            'is_active.boolean' => 'Status aktif harus berupa boolean.',
        ];
    }
}
