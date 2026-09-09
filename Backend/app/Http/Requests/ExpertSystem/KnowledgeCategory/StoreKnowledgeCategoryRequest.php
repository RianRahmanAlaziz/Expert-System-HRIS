<?php

namespace App\Http\Requests\ExpertSystem\KnowledgeCategory;


use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeCategoryRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100'
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:knowledge_categories,code'
            ],
            'description' => [
                'nullable',
                'string'
            ],
            'status' => [
                'required',
                'string',
                'max:30'
            ],
        ];
    }
}
