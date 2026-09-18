<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerformanceIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $indicator = $this->route('indicator');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('performance_indicators', 'code')->ignore($indicator?->id),
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

            'weight' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],

            'target' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'unit' => [
                'nullable',
                'string',
                'max:50',
            ],

            'status' => [
                'sometimes',
                'string',
                'max:30',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.string' => 'Kode indikator harus berupa teks.',
            'code.max' => 'Kode indikator maksimal 50 karakter.',
            'code.unique' => 'Kode indikator sudah digunakan.',
            'name.string' => 'Nama indikator performance harus berupa teks.',
            'name.max' => 'Nama indikator performance maksimal 150 karakter.',
            'description.string' => 'Deskripsi indikator harus berupa teks.',
            'weight.numeric' => 'Bobot indikator harus berupa angka.',
            'weight.min' => 'Bobot indikator tidak boleh kurang dari 0.',
            'weight.max' => 'Bobot indikator tidak boleh lebih dari 100.',
            'target.numeric' => 'Target harus berupa angka.',
            'target.min' => 'Target tidak boleh kurang dari 0.',
            'unit.string' => 'Satuan indikator harus berupa teks.',
            'unit.max' =>  'Satuan indikator maksimal 50 karakter.',
            'status.string' =>  'Status indikator harus berupa teks.',
            'status.max' =>  'Status indikator maksimal 30 karakter.',
        ];
    }
}
