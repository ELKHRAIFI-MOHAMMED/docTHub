<?php

namespace App\Services\Orientation;

use App\Models\OrientationRule;

/**
 * Deterministic evaluation of rule clauses. No database access: everything comes from AnswerContext.
 *
 * Clause forms inside `orientation_rules.conditions` / used by question conditions:
 *   {"question": "<question code>", "operator": "in", "value": ["a", "b"]}
 *   {"field": "age" | "sex", "operator": "gte", "value": 65}
 */
class RuleEvaluator
{
    public function matches(OrientationRule $rule, AnswerContext $ctx): bool
    {
        $hasCriterion = false;

        if ($rule->body_part_id !== null) {
            $hasCriterion = true;
            if (! in_array((int) $rule->body_part_id, $ctx->bodyPartIds, true)) {
                return false;
            }
        }

        if ($rule->question_id !== null) {
            $hasCriterion = true;
            $answer = $ctx->answerForQuestionId((int) $rule->question_id);
            if (! $this->matchesClause($answer, (string) ($rule->operator ?? 'answered'), $rule->expected_value)) {
                return false;
            }
        }

        foreach ((array) ($rule->conditions ?? []) as $clause) {
            $hasCriterion = true;
            if (! is_array($clause) || ! $this->matchesConditionClause($clause, $ctx)) {
                return false;
            }
        }

        // A rule without any criterion must never fire for everybody.
        return $hasCriterion;
    }

    public function matchesConditionClause(array $clause, AnswerContext $ctx): bool
    {
        $operator = (string) ($clause['operator'] ?? 'equals');
        $expected = $clause['value'] ?? null;

        if (isset($clause['field'])) {
            $answer = match ($clause['field']) {
                'age' => $ctx->age !== null ? ['options' => [], 'value' => (string) $ctx->age] : null,
                'sex' => $ctx->sex !== null ? ['options' => [$ctx->sex], 'value' => $ctx->sex] : null,
                default => null,
            };

            return $this->matchesClause($answer, $operator, $expected);
        }

        if (isset($clause['question'])) {
            return $this->matchesClause($ctx->answerForCode((string) $clause['question']), $operator, $expected);
        }

        return false;
    }

    /** @param  array{options: string[], value: ?string}|null  $answer */
    public function matchesClause(?array $answer, string $operator, mixed $expected): bool
    {
        if ($answer === null) {
            return false;
        }

        $values = $answer['options'] ?: ($answer['value'] !== null && $answer['value'] !== '' ? [(string) $answer['value']] : []);
        if (! $values) {
            return false;
        }

        $expectedList = array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v, is_array($expected) ? $expected : [$expected]);
        $numeric = $answer['value'] ?? ($answer['options'][0] ?? null);

        return match ($operator) {
            'answered' => true,
            'equals', 'contains', 'in' => (bool) array_intersect($values, $expectedList),
            'not_equals', 'not_in' => ! array_intersect($values, $expectedList),
            'gte' => is_numeric($numeric) && is_numeric($expectedList[0] ?? null) && (float) $numeric >= (float) $expectedList[0],
            'lte' => is_numeric($numeric) && is_numeric($expectedList[0] ?? null) && (float) $numeric <= (float) $expectedList[0],
            default => false,
        };
    }
}
