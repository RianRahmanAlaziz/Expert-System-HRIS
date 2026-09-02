<?php

namespace App\Http\Requests\Performance;


use Illuminate\Foundation\Http\FormRequest;

class StorePerformancePeriodRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100'
            ],
            'start_date' => [
                'required',
                'date'
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date'
            ],
            'status' => [
                'nullable',
                'string',
                'in:draft,open,closed'
            ],
            'description' => [
                'nullable',
                'string'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama periode performance wajib diisi.',
            'name.string' => 'Nama periode performance harus berupa teks.',
            'name.max' => 'Nama periode performance maksimal 100 karakter.',

            'start_date.required' => 'Tanggal mulai periode wajib diisi.',
            'start_date.date' => 'Tanggal mulai periode harus berupa tanggal yang valid.',

            'end_date.required' => 'Tanggal selesai periode wajib diisi.',
            'end_date.date' => 'Tanggal selesai periode harus berupa tanggal yang valid.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',

            'status.in' => 'Status periode harus draft, open, atau closed.',

            'description.string' => 'Deskripsi periode harus berupa teks.',
        ];
    }
}
