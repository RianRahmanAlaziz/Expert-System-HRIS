<?php

namespace App\Http\Requests\ExpertSystem\Knowledge;


use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgeRequest extends FormRequest
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
            'knowledge_category_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:knowledge_categories,id',
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:200'
            ],
            'description' => [
                'sometimes',
                'required',
                'string'
            ],
            'version' => [
                'sometimes',
                'integer',
                'min:1'
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
