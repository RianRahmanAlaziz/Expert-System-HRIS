<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('end_date')) {
                return;
            }

            $period = $this->route('period');

            if (! $period) {
                return;
            }

            $startDate = $this->input(
                'start_date',
                $period->start_date?->toDateString(),
            );

            $endDate = $this->input('end_date');

            if (
                $startDate !== null &&
                $endDate !== null &&
                $endDate < $startDate
            ) {
                $validator->errors()->add(
                    'end_date',
                    'Tanggal selesai harus sama atau setelah tanggal mulai.',
                );
            }
        });
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
