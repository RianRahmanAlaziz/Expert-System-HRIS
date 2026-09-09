<?php

namespace App\Http\Requests\ExpertSystem\ExpertRule;


use Illuminate\Foundation\Http\FormRequest;

class StoreExpertRuleRequest extends FormRequest
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
            'knowledge_id' => [
                'required',
                'integer',
                'exists:knowledge,id',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:expert_rules,code'
            ],
            'name' => [
                'required',
                'string',
                'max:200'
            ],
            'description' => [
                'nullable',
                'string'
            ],
            'priority' => [
                'sometimes',
                'integer',
                'min:1'
            ],
            'status' => [
                'required',
                'string',
                'max:30'
            ],
        ];
    }
}
