<?php

namespace App\Http\Requests\Training;

use App\Models\Training;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingRequest extends FormRequest
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
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('trainings', 'code')->ignore($this->route('training')),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:200',
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'trainer' => [
                'nullable',
                'string',
                'max:150',
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
            'capacity' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'status' => [
                'sometimes',
                'string',
                'max:30',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $training = $this->route('training');

        if (! $training instanceof Training) {
            return;
        }

        $this->merge([
            'start_date' => $this->input(
                'start_date',
                $training->start_date?->format('Y-m-d'),
            ),
            'end_date' => $this->input(
                'end_date',
                $training->end_date?->format('Y-m-d'),
            ),
        ]);
    }
}
