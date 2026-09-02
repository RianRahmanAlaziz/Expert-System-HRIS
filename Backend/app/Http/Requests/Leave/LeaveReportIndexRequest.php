<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class LeaveReportIndexRequest extends FormRequest
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

            'leave_type_id' => [
                'nullable',
                'integer',
                'exists:leave_types,id',
            ],

            'year' => [
                'nullable',
                'integer',
                'min:2000',
                'max:2100',
            ],

            'status' => [
                'nullable',
                'string',
                'in:pending,approved,rejected,cancelled',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
