<?php

namespace App\Http\Requests\Consultation;


use Illuminate\Foundation\Http\FormRequest;

class StoreExpertConsultationRequest extends FormRequest
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
            'consultation_type' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
