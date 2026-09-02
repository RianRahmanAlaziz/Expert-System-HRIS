<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class StorePerformanceIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100'
            ],
            'description' => [
                'nullable',
                'string'
            ],
            'category' => [
                'nullable',
                'string',
                'max:50'
            ],
            'target' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'weight' => [
                'required',
                'numeric',
                'min:0',
                'max:100'
            ],
            'measurement_type' => [
                'required',
                'string',
                'max:30',
            ],
            'is_active' => [
                'sometimes',
                'boolean'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama indikator performance wajib diisi.',
            'name.string' => 'Nama indikator performance harus berupa teks.',
            'name.max' => 'Nama indikator performance maksimal 100 karakter.',

            'description.string' => 'Deskripsi indikator harus berupa teks.',

            'category.string' => 'Kategori indikator harus berupa teks.',
            'category.max' => 'Kategori indikator maksimal 50 karakter.',

            'target.numeric' => 'Target harus berupa angka.',
            'target.min' => 'Target tidak boleh kurang dari 0.',

            'weight.required' => 'Bobot indikator wajib diisi.',
            'weight.numeric' => 'Bobot indikator harus berupa angka.',
            'weight.min' => 'Bobot indikator tidak boleh kurang dari 0.',
            'weight.max' => 'Bobot indikator tidak boleh lebih dari 100.',

            'measurement_type.required' => 'Tipe pengukuran wajib diisi.',
            'measurement_type.string' => 'Tipe pengukuran harus berupa teks.',
            'measurement_type.max' => 'Tipe pengukuran maksimal 30 karakter.',

            'is_active.boolean' => 'Status aktif harus berupa true atau false.',
        ];
    }
}
