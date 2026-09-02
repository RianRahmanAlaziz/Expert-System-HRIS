<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformancePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'start_date' => [
                'sometimes',
                'date',
            ],
            'end_date' => [
                'sometimes',
                'date',
                'after_or_equal:start_date',
            ],
            'status' => [
                'sometimes',
                'string',
                'in:draft,open,closed',
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
            'name.string' => 'Nama periode performance harus berupa teks.',
            'name.max' => 'Nama periode performance maksimal 100 karakter.',

            'start_date.date' => 'Tanggal mulai periode harus berupa tanggal yang valid.',

            'end_date.date' => 'Tanggal selesai periode harus berupa tanggal yang valid.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',

            'status.in' => 'Status periode harus draft, open, atau closed.',

            'description.string' => 'Deskripsi periode harus berupa teks.',
        ];
    }
}
