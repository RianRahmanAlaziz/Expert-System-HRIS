<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class PerformancePeriodIndexRequest extends FormRequest
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
            'status' => [
                'nullable',
                'string',
                'in:draft,open,closed',
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
            'search.string' => 'Pencarian periode performance harus berupa teks.',
            'search.max' => 'Pencarian periode performance maksimal 100 karakter.',

            'status.string' => 'Status periode performance harus berupa teks.',
            'status.in' => 'Status periode harus draft, open, atau closed.',

            'per_page.integer' => 'Jumlah data per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah data per halaman minimal 1.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
