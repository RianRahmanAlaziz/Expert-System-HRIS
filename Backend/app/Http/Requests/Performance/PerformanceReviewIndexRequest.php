<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerformanceReviewIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'employee_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'performance_period_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'draft',
                    'submitted',
                    'approved',
                    'rejected',
                ]),
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
            'search.string' =>  'Pencarian harus berupa teks.',
            'search.max' => 'Pencarian maksimal 100 karakter.',
            'employee_id.integer' => 'Employee ID harus berupa angka.',
            'employee_id.min' => 'Employee ID minimal 1.',
            'performance_period_id.integer' => 'Performance period ID harus berupa angka.',
            'performance_period_id.min' => 'Performance period ID minimal 1.',
            'status.in' => 'Status review tidak valid.',
            'per_page.integer' => 'Jumlah data per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah data per halaman minimal 1.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
