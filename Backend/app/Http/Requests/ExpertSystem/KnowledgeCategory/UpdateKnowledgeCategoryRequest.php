<?php

namespace App\Http\Requests\ExpertSystem\KnowledgeCategory;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeCategoryRequest extends FormRequest
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
                'sometimes',
                'required',
                'string',
                'max:100'
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('knowledge_categories', 'code')->ignore($this->route('knowledgeCategory')),
            ],
            'description' => [
                'nullable',
                'string'
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                'max:30'
            ],
        ];
    }
}
