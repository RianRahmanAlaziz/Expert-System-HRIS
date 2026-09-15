<?php

namespace App\Http\Requests\SystemSupport;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
                'max:10240',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee wajib dipilih.',
            'employee_id.integer' => 'Employee tidak valid.',
            'employee_id.exists' => 'Employee tidak ditemukan.',
            'name.required' => 'Nama dokumen wajib diisi.',
            'name.string' => 'Nama dokumen harus berupa teks.',
            'name.max' => 'Nama dokumen maksimal 255 karakter.',
            'file.required' => 'File wajib diunggah.',
            'file.file' => 'File yang diunggah tidak valid.',
            'file.mimes' => 'Format file harus berupa PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
            'description.string' => 'Deskripsi harus berupa teks.',
        ];
    }
}
