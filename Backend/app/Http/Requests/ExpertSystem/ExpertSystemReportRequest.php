<?php

namespace App\Http\Requests\ExpertSystem;

use Illuminate\Foundation\Http\FormRequest;

class ExpertSystemReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
            ],
            'consultation_type' => [
                'nullable',
                'string',
                'max:100',
            ],
            'recommendation' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.integer' => 'Employee ID harus berupa angka.',
            'employee_id.exists' => 'Employee tidak ditemukan.',
            'consultation_type.string' => 'Tipe konsultasi harus berupa teks.',
            'consultation_type.max' => 'Tipe konsultasi maksimal 100 karakter.',
            'recommendation.string' => 'Recommendation harus berupa teks.',
            'recommendation.max' => 'Recommendation maksimal 100 karakter.',
        ];
    }
}
