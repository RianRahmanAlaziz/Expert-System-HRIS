<?php

namespace App\Http\Requests\Competency;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeCompetencyRequest extends FormRequest
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
        $employeeCompetency = $this->route('employeeCompetency');

        return [
            'employee_id' => [
                'sometimes',
                'integer',
                'exists:employees,id',
            ],
            'competency_id' => [
                'sometimes',
                'integer',
                'exists:competencies,id',
                Rule::unique('employee_competencies', 'competency_id')
                    ->where(
                        fn($query) => $query->where(
                            'employee_id',
                            $this->input(
                                'employee_id',
                                $employeeCompetency?->employee_id,
                            ),
                        ),
                    )->ignore($employeeCompetency?->id),
            ],
            'competency_level_id' => [
                'sometimes',
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
