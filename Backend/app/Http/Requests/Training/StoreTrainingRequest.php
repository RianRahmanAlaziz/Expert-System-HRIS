<?php

namespace App\Http\Requests\Training;


use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingRequest extends FormRequest
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
                'required',
                'string',
                'max:50',
                'unique:trainings,code',
            ],
            'name' => [
                'required',
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
                'required',
                'date',
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
            'capacity' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'status' => [
                'required',
                'string',
                'max:30',
            ],
        ];
    }
}
