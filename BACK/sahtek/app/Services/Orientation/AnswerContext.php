<?php

namespace App\Services\Orientation;

use App\Models\QuestionOption;
use App\Models\SymptomAssessment;

/**
 * Immutable snapshot of what the patient reported, used by the rule evaluator and question flow.
 * An answer is normalised to ['options' => string[], 'value' => ?string].
 */
final class AnswerContext
{
    /**
     * @param  array<int, array{options: string[], value: ?string}>  $byQuestionId
     * @param  array<string, int>  $questionIdsByCode
     * @param  int[]  $bodyPartIds
     * @param  int[]  $symptomIds  symptoms reported through options linked to a symptom
     */
    public function __construct(
        public readonly array $byQuestionId,
        public readonly array $questionIdsByCode,
        public readonly array $bodyPartIds,
        public readonly array $symptomIds = [],
        public readonly ?string $sex = null,
        public readonly ?int $age = null,
    ) {
    }

    public static function fromAssessment(SymptomAssessment $assessment): self
    {
        $answers = $assessment->answers()->with('question:id,code')->get();

        $byId = [];
        $idsByCode = [];
        $optionPairs = [];
        foreach ($answers as $answer) {
            $byId[$answer->question_id] = [
                'options' => array_values(array_map('strval', $answer->option_codes ?? [])),
                'value' => $answer->value,
            ];
            if ($answer->question) {
                $idsByCode[$answer->question->code] = $answer->question_id;
            }
            foreach ($answer->option_codes ?? [] as $code) {
                $optionPairs[] = [$answer->question_id, $code];
            }
        }

        $symptomIds = [];
        if ($optionPairs) {
            $symptomIds = QuestionOption::query()
                ->whereNotNull('symptom_id')
                ->where(function ($q) use ($optionPairs) {
                    foreach ($optionPairs as [$questionId, $code]) {
                        $q->orWhere(fn ($w) => $w->where('question_id', $questionId)->where('code', $code));
                    }
                })
                ->pluck('symptom_id')->unique()->values()->all();
        }

        return new self(
            $byId,
            $idsByCode,
            array_map('intval', $assessment->body_part_ids ?? []),
            $symptomIds,
            $assessment->sex,
            $assessment->age_years,
        );
    }

    public function answerForQuestionId(?int $id): ?array
    {
        return $id !== null ? ($this->byQuestionId[$id] ?? null) : null;
    }

    public function answerForCode(string $code): ?array
    {
        return $this->answerForQuestionId($this->questionIdsByCode[$code] ?? null);
    }
}
