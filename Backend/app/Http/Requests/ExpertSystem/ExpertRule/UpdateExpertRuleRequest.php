<?php

namespace App\Http\Requests\ExpertSystem\ExpertRule;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpertRuleRequest extends FormRequest
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
                'sometimes',
                'required',
                'integer',
                'exists:knowledge,id',
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('expert_rules', 'code')->ignore($this->route('expertRule')),
            ],
            'name' => [
                'sometimes',
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
                'sometimes',
                'required',
                'string',
                'max:30'
            ],
        ];
    }
}
