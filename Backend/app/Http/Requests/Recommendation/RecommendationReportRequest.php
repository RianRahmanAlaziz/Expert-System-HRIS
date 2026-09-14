<?php

namespace App\Http\Requests\Recommendation;

use Illuminate\Foundation\Http\FormRequest;

class RecommendationReportRequest extends FormRequest
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
            'type' => [
                'nullable',
                'string',
                'in:promotion,training,career,performance_improvement,employee_risk',
            ],
            'status' => [
                'nullable',
                'string',
                'in:pending,approved,rejected,implemented',
            ],
            'priority' => [
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.integer' => 'Employee ID harus berupa angka.',
            'employee_id.exists' => 'Employee tidak ditemukan.',
            'type.in' => 'Tipe recommendation tidak valid.',
            'status.in' => 'Status recommendation tidak valid.',
            'priority.max' => 'Priority maksimal 30 karakter.',
        ];
    }
}
