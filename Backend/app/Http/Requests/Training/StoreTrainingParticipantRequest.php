<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingParticipantRequest extends FormRequest
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
            'training_id' => [
                'required',
                'integer',
                'exists:trainings,id',
            ],
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
            ],
            'status' => [
                'sometimes',
                'string',
                'max:30',
            ],
            'registered_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
