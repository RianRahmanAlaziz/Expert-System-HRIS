<?php

namespace App\Services\ExpertSystem;

use Illuminate\Support\Collection;

final class InferenceEngine
{
    public function __construct(
        private readonly ConditionEvaluator $conditionEvaluator,
    ) {}

    public function evaluate(
        Collection $rules,
        array $facts,
    ): array {
        $matchedRules = [];
        $suggestedActions = [];

        $totalConditions = 0;
        $passedConditions = 0;

        foreach ($rules as $rule) {
            $conditions = $rule->conditions
                ->sortBy('sort_order')
                ->values();

            if ($conditions->isEmpty()) {
                continue;
            }

            $results = [];
            $matched = null;

            foreach ($conditions as $condition) {
                $exists = array_key_exists(
                    $condition->parameter,
                    $facts,
                );

                $passed = $exists
                    && $this->conditionEvaluator->evaluate(
                        actual: $facts[$condition->parameter],
                        operator: $condition->operator,
                        expected: $condition->value,
                    );

                $results[] = [
                    'parameter' => $condition->parameter,
                    'operator' => $condition->operator,
                    'value' => $condition->value,
                    'logical_operator' =>
                    $condition->logical_operator,
                    'passed' => $passed,
                ];

                $totalConditions++;

                if ($passed) {
                    $passedConditions++;
                }

                $matched = $matched === null
                    ? $passed
                    : $this->combine(
                        $matched,
                        $passed,
                        $condition->logical_operator,
                    );
            }

            if ($matched !== true) {
                continue;
            }

            $matchedRules[] = [
                'id' => $rule->id,
                'code' => $rule->code,
                'name' => $rule->name,
                'priority' => $rule->priority,
                'conditions' => $results,
            ];

            foreach ($rule->actions as $action) {
                $suggestedActions[] = [
                    'action_type' => $action->action_type,
                    'action_value' => $action->action_value,
                    'description' => $action->description,
                ];
            }
        }

        return [
            'matched_rules' => $matchedRules,
            'suggested_actions' => $suggestedActions,
            'total_conditions' => $totalConditions,
            'passed_conditions' => $passedConditions,
        ];
    }

    private function combine(
        bool $current,
        bool $next,
        ?string $operator,
    ): bool {
        return strtoupper($operator ?? 'AND') === 'OR'
            ? $current || $next
            : $current && $next;
    }
}
