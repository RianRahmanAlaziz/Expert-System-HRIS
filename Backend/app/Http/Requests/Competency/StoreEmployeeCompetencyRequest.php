<?php

namespace App\Http\Requests\Competency;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeCompetencyRequest extends FormRequest
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
                'required',
                'integer',
                'exists:employees,id',
            ],
            'competency_id' => [
                'required',
                'integer',
                'exists:competencies,id',
                Rule::unique('employee_competencies', 'competency_id')
                    ->where(
                        fn($query) => $query->where(
                            'employee_id',
                            $this->input('employee_id'),
                        ),
                    ),
            ],
            'competency_level_id' => [
                'required',
                'integer',
                'exists:competency_levels,id',
            ],
            'score' => [
                'nullable',
                'numeric',
                'between:0,999.99',
            ],
            'assessed_at' => [
                'nullable',
                'date',
            ],
            'assessed_by' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
