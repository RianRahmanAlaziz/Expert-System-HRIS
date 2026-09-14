<?php

namespace App\Http\Requests\Competency;

use Illuminate\Foundation\Http\FormRequest;

class CompetencyReportRequest extends FormRequest
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

            'competency_id' => [
                'nullable',
                'integer',
                'exists:competencies,id',
            ],

            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'position_id' => [
                'nullable',
                'integer',
                'exists:positions,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.integer' =>  'Employee ID harus berupa angka.',
            'employee_id.exists' =>  'Employee tidak ditemukan.',
            'competency_id.integer' => 'Competency ID harus berupa angka.',
            'competency_id.exists' => 'Competency tidak ditemukan.',
            'department_id.integer' => 'Department ID harus berupa angka.',
            'department_id.exists' => 'Department tidak ditemukan.',
            'position_id.integer' => 'Position ID harus berupa angka.',
            'position_id.exists' =>  'Position tidak ditemukan.',
        ];
    }
}
