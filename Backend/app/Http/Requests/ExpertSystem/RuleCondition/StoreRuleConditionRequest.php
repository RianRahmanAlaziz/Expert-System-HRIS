<?php

namespace App\Http\Requests\ExpertSystem\RuleCondition;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRuleConditionRequest extends FormRequest
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
            'parameter' => [
                'required',
                'string',
                'max:100'
            ],
            'operator' => [
                'required',
                'string',
                Rule::in(['>', '<', '>=', '<=', '=']),
            ],
            'value' => [
                'required',
                'string',
                'max:255'
            ],
            'logical_operator' => [
                'nullable',
                'string',
                Rule::in(['AND', 'OR']),
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0'
            ],
        ];
    }
}
