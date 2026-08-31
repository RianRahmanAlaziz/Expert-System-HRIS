<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceReportRequest extends FormRequest
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
            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'present',
                    'late',
                    'absent',
                ]),
            ],

            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date' => 'Tanggal mulai tidak valid.',
            'end_date.date' => 'Tanggal akhir tidak valid.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            'status.in' => 'Status attendance tidak valid.',
            'employee_id.integer' => 'Employee ID harus berupa angka.',
            'employee_id.exists' => 'Employee tidak ditemukan.',
        ];
    }
}
