<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeIndexRequest extends FormRequest
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
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'position_id' => [
                'nullable',
                'integer',
                'exists:positions,id',
            ],

            'manager_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
            ],

            'employment_type' => [
                'nullable',
                'string',
                'max:50',
            ],

            'employment_status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Pencarian harus berupa teks.',
            'search.max' => 'Pencarian maksimal 100 karakter.',

            'department_id.integer' => 'Department tidak valid.',
            'department_id.exists' => 'Department tidak ditemukan.',

            'position_id.integer' => 'Position tidak valid.',
            'position_id.exists' => 'Position tidak ditemukan.',

            'manager_id.integer' => 'Manager tidak valid.',
            'manager_id.exists' => 'Manager tidak ditemukan.',

            'employment_type.string' => 'Tipe employment harus berupa teks.',
            'employment_type.max' => 'Tipe employment maksimal 50 karakter.',

            'employment_status.string' => 'Status employment harus berupa teks.',
            'employment_status.max' => 'Status employment maksimal 50 karakter.',

            'per_page.integer' => 'Jumlah data per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah data per halaman minimal 1.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
