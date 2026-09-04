<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainingParticipantRequest extends FormRequest
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
            'status' => [
                'sometimes',
                'string',
                'max:30',
            ],
            'registered_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'completed_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'certificate_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
