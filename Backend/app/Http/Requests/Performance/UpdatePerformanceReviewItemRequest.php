<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceReviewItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'performance_indicator_id' => [
                'sometimes',
                'integer',
                'exists:performance_indicators,id',
            ],
            'score' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
            ],
            'comment' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'performance_indicator_id.integer' =>
            'Performance indicator harus berupa angka.',

            'performance_indicator_id.exists' =>
            'Performance indicator yang dipilih tidak ditemukan.',

            'score.numeric' =>
            'Score harus berupa angka.',

            'score.min' =>
            'Score tidak boleh kurang dari 0.',

            'score.max' =>
            'Score tidak boleh lebih dari 100.',

            'comment.string' =>
            'Komentar harus berupa teks.',
        ];
    }
}
