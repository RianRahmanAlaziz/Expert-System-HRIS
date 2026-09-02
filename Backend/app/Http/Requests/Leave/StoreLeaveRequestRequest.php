<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => [
                'required',
                'integer',
                'exists:leave_types,id',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'reason' => [
                'required',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.required' => 'Jenis cuti wajib dipilih.',
            'leave_type_id.integer' => 'Jenis cuti tidak valid.',
            'leave_type_id.exists' => 'Jenis cuti tidak ditemukan.',

            'start_date.required' => 'Tanggal mulai cuti wajib diisi.',
            'start_date.date' => 'Tanggal mulai cuti tidak valid.',

            'end_date.required' => 'Tanggal selesai cuti wajib diisi.',
            'end_date.date' => 'Tanggal selesai cuti tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai cuti harus setelah atau sama dengan tanggal mulai.',

            'reason.required' => 'Alasan cuti wajib diisi.',
            'reason.string' => 'Alasan cuti harus berupa teks.',
        ];
    }
}
