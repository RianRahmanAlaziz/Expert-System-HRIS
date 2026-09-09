<?php

namespace App\Http\Requests\ExpertSystem\RuleAction;


use Illuminate\Foundation\Http\FormRequest;

class StoreRuleActionRequest extends FormRequest
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
            'expert_rule_id' => [
                'required',
                'integer',
                'exists:expert_rules,id',
            ],
            'action_type' => [
                'required',
                'string',
                'max:50'
            ],
            'action_value' => [
                'required',
                'string',
                'max:255'
            ],
            'description' => [
                'nullable',
                'string'
            ],
        ];
    }
}
