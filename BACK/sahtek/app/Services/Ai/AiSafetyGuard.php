<?php

namespace App\Services\Ai;

use App\Models\BodyPart;
use App\Models\QuestionOption;
use App\Models\Symptom;
use App\Models\SymptomQuestion;

/**
 * Validates AI output before it can influence an assessment.
 * Anything not present in the published catalog is dropped, so an AI can never introduce
 * a condition, a treatment or a question of its own. Emergency evaluation stays with the rule engine.
 */
class AiSafetyGuard
{
    public function sanitize(AssistantSuggestion $suggestion): AssistantSuggestion
    {
        $bodyParts = BodyPart::published()->whereIn('code', $suggestion->bodyPartCodes)->pluck('code')->all();
        $symptoms = Symptom::published()->whereIn('code', $suggestion->symptomCodes)->pluck('code')->all();

        $answers = [];
        if ($suggestion->answers) {
            $questions = SymptomQuestion::published()->whereIn('code', array_keys($suggestion->answers))->get()->keyBy('code');
            foreach ($suggestion->answers as $code => $answer) {
                $question = $questions->get($code);
                if (! $question) {
                    continue;
                }
                if ($question->hasOptions()) {
                    $valid = QuestionOption::where('question_id', $question->id)->whereIn('code', (array) $answer)->pluck('code')->all();
                    if ($valid) {
                        $answers[$code] = $question->type === 'single_choice' ? [$valid[0]] : $valid;
                    }
                } elseif (is_scalar($answer) && is_numeric($answer)) {
                    $n = (float) $answer;
                    if (($question->min_value === null || $n >= $question->min_value) && ($question->max_value === null || $n <= $question->max_value)) {
                        $answers[$code] = (string) $answer;
                    }
                }
            }
        }

        return new AssistantSuggestion($bodyParts, $symptoms, $answers);
    }

    /** Removes direct identifiers before text could leave the platform. */
    public static function redactIdentifiers(string $text): string
    {
        $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $text);
        $text = preg_replace('/\+?\d[\d\s().-]{7,}\d/', '[phone]', $text);

        return $text;
    }
}
