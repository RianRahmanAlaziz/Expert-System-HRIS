<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerformanceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
            ],

            'performance_period_id' => [
                'required',
                'integer',
                'exists:performance_periods,id',
            ],

            'review_type' => [
                'required',
                Rule::in([
                    'self',
                    'manager',
                ]),
            ],

            'review_date' => [
                'nullable',
                'date',
            ],

            'comments' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' =>
            'Employee wajib dipilih.',

            'employee_id.integer' =>
            'Employee ID harus berupa angka.',

            'employee_id.exists' =>
            'Employee yang dipilih tidak ditemukan.',

            'performance_period_id.required' =>
            'Performance period wajib dipilih.',

            'performance_period_id.integer' =>
            'Performance period ID harus berupa angka.',

            'performance_period_id.exists' =>
            'Performance period yang dipilih tidak ditemukan.',

            'review_type.required' =>
            'Review type wajib dipilih.',

            'review_type.in' =>
            'Review type harus self atau manager.',

            'review_date.date' =>
            'Review date harus berupa tanggal yang valid.',

            'comments.string' =>
            'Komentar harus berupa teks.',
        ];
    }
}
