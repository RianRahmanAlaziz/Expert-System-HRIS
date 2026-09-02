<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class PerformanceReportRequest extends FormRequest
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
            'period_id' => [
                'nullable',
                'integer',
                'exists:performance_periods,id',
            ],

            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
            ],

            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'review_type' => [
                'nullable',
                'string',
                'in:self,manager',
            ],
        ];
    }
}
