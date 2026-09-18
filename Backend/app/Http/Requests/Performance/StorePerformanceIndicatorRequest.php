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
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:performance_indicators,code',
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

            'weight' => [
                'required',
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
                'required',
                'string',
                'max:30',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode indikator wajib diisi.',
            'code.string' => 'Kode indikator harus berupa teks.',
            'code.max' => 'Kode indikator maksimal 50 karakter.',
            'code.unique' => 'Kode indikator sudah digunakan.',
            'name.required' =>  'Nama indikator performance wajib diisi.',
            'name.string' => 'Nama indikator performance harus berupa teks.',
            'name.max' => 'Nama indikator performance maksimal 150 karakter.',
            'description.string' =>  'Deskripsi indikator harus berupa teks.',
            'weight.required' => 'Bobot indikator wajib diisi.',
            'weight.numeric' =>  'Bobot indikator harus berupa angka.',
            'weight.min' =>  'Bobot indikator tidak boleh kurang dari 0.',
            'weight.max' =>   'Bobot indikator tidak boleh lebih dari 100.',
            'target.numeric' => 'Target harus berupa angka.',
            'target.min' =>  'Target tidak boleh kurang dari 0.',
            'unit.string' => 'Satuan indikator harus berupa teks.',
            'unit.max' => 'Satuan indikator maksimal 50 karakter.',
            'status.required' =>  'Status indikator wajib diisi.',
            'status.string' =>  'Status indikator harus berupa teks.',
            'status.max' => 'Status indikator maksimal 30 karakter.',
        ];
    }
}
