<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class PerformanceIndicatorIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_active')) {
            return;
        }

        $isActive = $this->query('is_active');

        if ($isActive === 'true') {
            $this->merge([
                'is_active' => true,
            ]);
        }

        if ($isActive === 'false') {
            $this->merge([
                'is_active' => false,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'category' => [
                'nullable',
                'string',
                'max:50',
            ],
            'is_active' => [
                'nullable',
                'boolean',
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
            'search.string' => 'Pencarian indikator performance harus berupa teks.',
            'search.max' => 'Pencarian indikator performance maksimal 100 karakter.',

            'category.string' => 'Kategori indikator harus berupa teks.',
            'category.max' => 'Kategori indikator maksimal 50 karakter.',

            'is_active.boolean' => 'Status aktif harus berupa true atau false.',

            'per_page.integer' => 'Jumlah data per halaman harus berupa angka.',
            'per_page.min' => 'Jumlah data per halaman minimal 1.',
            'per_page.max' => 'Jumlah data per halaman maksimal 100.',
        ];
    }
}
