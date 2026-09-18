<?php

namespace App\Http\Requests\ExpertSystem\RuleCondition;

use App\Services\ExpertSystem\ExpertParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRuleConditionRequest extends FormRequest
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
                'sometimes',
                'required',
                'integer',
                'exists:expert_rules,id',
            ],
            'parameter' => [
                'sometimes',
                'required',
                'string',
                Rule::in(ExpertParameter::values()),
            ],
            'operator' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['>', '<', '>=', '<=', '=']),
            ],
            'value' => [
                'sometimes',
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
