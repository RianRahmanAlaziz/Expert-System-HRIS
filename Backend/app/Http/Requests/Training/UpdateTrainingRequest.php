<?php

namespace App\Http\Requests\Training;


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
}
