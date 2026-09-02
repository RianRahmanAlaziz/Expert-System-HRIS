<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class LeaveBalanceIndexRequest extends FormRequest
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
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
