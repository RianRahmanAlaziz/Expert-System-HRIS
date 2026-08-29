<?php

namespace App\Http\Requests\Position;


use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
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
                'unique:positions,code',
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
            'level' => [
                'required',
                'integer',
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
            'code.required' => 'Kode position wajib diisi.',
            'code.string' => 'Kode position harus berupa teks.',
            'code.max' => 'Kode position maksimal 50 karakter.',
            'code.unique' => 'Kode position sudah digunakan.',

            'name.required' => 'Nama position wajib diisi.',
            'name.string' => 'Nama position harus berupa teks.',
            'name.max' => 'Nama position maksimal 150 karakter.',

            'description.string' => 'Deskripsi position harus berupa teks.',

            'level.required' => 'Level position wajib diisi.',
            'level.integer' => 'Level position harus berupa angka.',

            'status.required' => 'Status position wajib diisi.',
            'status.string' => 'Status position harus berupa teks.',
            'status.max' => 'Status position maksimal 30 karakter.',

            'is_active.boolean' => 'Status aktif harus berupa boolean.',
        ];
    }
}
