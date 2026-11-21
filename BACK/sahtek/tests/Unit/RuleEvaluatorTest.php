<?php

namespace Tests\Unit;

use App\Models\OrientationRule;
use App\Services\Ai\AiSafetyGuard;
use App\Services\Orientation\AnswerContext;
use App\Services\Orientation\RuleEvaluator;
use PHPUnit\Framework\TestCase;

class RuleEvaluatorTest extends TestCase
{
    private RuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new RuleEvaluator();
    }

    private function ctx(array $answers = [], array $bodyParts = [1], ?string $sex = null, ?int $age = null): AnswerContext
    {
        // $answers: [questionId => ['code' => ..., 'options' => [...], 'value' => ...]]
        $byId = [];
        $codes = [];
        foreach ($answers as $id => $a) {
            $byId[$id] = ['options' => $a['options'] ?? [], 'value' => $a['value'] ?? null];
            $codes[$a['code']] = $id;
        }

        return new AnswerContext($byId, $codes, $bodyParts, [], $sex, $age);
    }

    private function rule(array $attrs): OrientationRule
    {
        return (new OrientationRule())->forceFill($attrs + ['body_part_id' => null, 'question_id' => null, 'conditions' => null]);
    }

    public function test_clause_operators(): void
    {
        $multi = ['options' => ['a', 'b'], 'value' => null];
        $num = ['options' => [], 'value' => '7'];

        $this->assertTrue($this->evaluator->matchesClause($multi, 'in', ['b', 'z']));
        $this->assertFalse($this->evaluator->matchesClause($multi, 'in', ['z']));
        $this->assertTrue($this->evaluator->matchesClause($multi, 'equals', 'a'));
        $this->assertTrue($this->evaluator->matchesClause($multi, 'not_in', ['z']));
        $this->assertFalse($this->evaluator->matchesClause($multi, 'not_equals', 'a'));
        $this->assertTrue($this->evaluator->matchesClause($num, 'gte', 7));
        $this->assertFalse($this->evaluator->matchesClause($num, 'gte', 8));
        $this->assertTrue($this->evaluator->matchesClause($num, 'lte', '7'));
        $this->assertTrue($this->evaluator->matchesClause($num, 'answered', null));
        $this->assertFalse($this->evaluator->matchesClause($multi, 'gte', 1), 'option codes are not numeric');
        $this->assertFalse($this->evaluator->matchesClause(null, 'not_in', ['z']), 'missing answer never matches');
        $this->assertFalse($this->evaluator->matchesClause($num, 'unknown', 7));
    }

    public function test_rule_needs_all_criteria(): void
    {
        $ctx = $this->ctx([10 => ['code' => 'q', 'options' => ['x']], 11 => ['code' => 'intensity', 'value' => '8']], [3]);

        $base = ['question_id' => 10, 'operator' => 'in', 'expected_value' => ['x']];
        $this->assertTrue($this->evaluator->matches($this->rule($base), $ctx));
        $this->assertTrue($this->evaluator->matches($this->rule($base + ['body_part_id' => 3]), $ctx));
        $this->assertFalse($this->evaluator->matches($this->rule($base + ['body_part_id' => 4]), $ctx));

        $withConditions = $base + ['conditions' => [['question' => 'intensity', 'operator' => 'gte', 'value' => 7]]];
        $this->assertTrue($this->evaluator->matches($this->rule($withConditions), $ctx));
        $withConditions['conditions'][] = ['question' => 'missing', 'operator' => 'answered'];
        $this->assertFalse($this->evaluator->matches($this->rule($withConditions), $ctx));
    }

    public function test_rule_without_any_criterion_never_matches(): void
    {
        $this->assertFalse($this->evaluator->matches($this->rule([]), $this->ctx()));
    }

    public function test_demographic_field_clauses(): void
    {
        $ctx = $this->ctx([], [1], 'female', 70);
        $rule = $this->rule(['conditions' => [['field' => 'age', 'operator' => 'gte', 'value' => 65], ['field' => 'sex', 'operator' => 'equals', 'value' => 'female']]]);
        $this->assertTrue($this->evaluator->matches($rule, $ctx));
        $this->assertFalse($this->evaluator->matches($rule, $this->ctx([], [1], 'female', null)));
    }

    public function test_ai_text_is_redacted_before_leaving_the_platform(): void
    {
        $text = AiSafetyGuard::redactIdentifiers('Call me at +212 6 12 34 56 78 or mail jane.doe@example.com about my headache');
        $this->assertStringNotContainsString('jane.doe@example.com', $text);
        $this->assertStringNotContainsString('34 56', $text);
        $this->assertStringContainsString('headache', $text);
    }
}
