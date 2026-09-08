<?php

namespace App\Http\Requests\Position;


use Illuminate\Foundation\Http\FormRequest;

class UpdatePositionRequirementRequest extends FormRequest
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
            'position_id' => [
                'sometimes',
                'integer',
                'exists:positions,id',
            ],

            'minimum_experience_years' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:999.99',
            ],

            'minimum_performance_score' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'minimum_attendance_percentage' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'status' => [
                'sometimes',
                'string',
                'max:30',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'position_id.integer' => 'Position ID harus berupa angka.',
            'position_id.exists' => 'Position tidak ditemukan.',

            'minimum_experience_years.numeric' => 'Minimum pengalaman harus berupa angka.',
            'minimum_experience_years.min' => 'Minimum pengalaman tidak boleh kurang dari 0.',
            'minimum_experience_years.max' => 'Minimum pengalaman maksimal 999.99 tahun.',

            'minimum_performance_score.numeric' => 'Minimum performance score harus berupa angka.',
            'minimum_performance_score.min' => 'Minimum performance score tidak boleh kurang dari 0.',
            'minimum_performance_score.max' => 'Minimum performance score maksimal 100.',

            'minimum_attendance_percentage.numeric' => 'Minimum attendance percentage harus berupa angka.',
            'minimum_attendance_percentage.min' => 'Minimum attendance percentage tidak boleh kurang dari 0.',
            'minimum_attendance_percentage.max' => 'Minimum attendance percentage maksimal 100.',

            'description.string' => 'Deskripsi requirement harus berupa teks.',

            'status.string' => 'Status requirement harus berupa teks.',
            'status.max' => 'Status requirement maksimal 30 karakter.',

            'is_active.boolean' => 'Status aktif harus berupa boolean.',
        ];
    }
}
