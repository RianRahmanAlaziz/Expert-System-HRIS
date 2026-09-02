<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
            'review_date.date' =>
            'Review date harus berupa tanggal yang valid.',

            'comments.string' =>
            'Komentar harus berupa teks.',
        ];
    }
}
