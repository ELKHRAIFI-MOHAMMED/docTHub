<?php

namespace App\Services\Orientation;

use App\Models\SymptomAssessment;
use App\Models\SymptomQuestion;
use Illuminate\Support\Collection;

/** Computes which questions are currently visible for an assessment (dynamic questionnaire). */
class QuestionFlow
{
    public function __construct(private readonly RuleEvaluator $evaluator)
    {
    }

    /** All published questions relevant to the selected body parts (global ones included). */
    public function candidateQuestions(SymptomAssessment $assessment): Collection
    {
        $bodyParts = array_map('intval', $assessment->body_part_ids ?? []);

        return SymptomQuestion::published()
            ->withLocaleTranslations()
            ->with(['options' => fn ($q) => $q->withLocaleTranslations(), 'conditions'])
            ->where(fn ($q) => $q->whereNull('body_part_id')->orWhereIn('body_part_id', $bodyParts))
            ->orderByRaw('CASE WHEN body_part_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function visibleQuestions(Collection $questions, AnswerContext $ctx): Collection
    {
        return $questions->filter(function (SymptomQuestion $question) use ($ctx) {
            foreach ($question->conditions as $condition) {
                $answer = $ctx->answerForQuestionId((int) $condition->depends_on_question_id);
                if (! $this->evaluator->matchesClause($answer, $condition->operator, $condition->value)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @return array{questions: Collection, answers: array, complete: bool, next_question_id: ?int}
     */
    public function state(SymptomAssessment $assessment): array
    {
        $ctx = AnswerContext::fromAssessment($assessment);
        $visible = $this->visibleQuestions($this->candidateQuestions($assessment), $ctx);

        $next = $visible->first(fn (SymptomQuestion $q) => $ctx->answerForQuestionId($q->id) === null && $q->is_required);
        $nextOptional = $visible->first(fn (SymptomQuestion $q) => $ctx->answerForQuestionId($q->id) === null);

        return [
            'questions' => $visible,
            'answers' => $ctx->byQuestionId,
            'complete' => $next === null,
            'next_question_id' => ($next ?? $nextOptional)?->id,
        ];
    }

    /** Question ids whose answers are no longer relevant after an answer changed. */
    public function hiddenAnsweredQuestionIds(SymptomAssessment $assessment): array
    {
        $ctx = AnswerContext::fromAssessment($assessment);
        $visibleIds = $this->visibleQuestions($this->candidateQuestions($assessment), $ctx)->pluck('id')->all();

        return array_values(array_diff(array_keys($ctx->byQuestionId), $visibleIds));
    }
}
