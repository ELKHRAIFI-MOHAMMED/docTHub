<?php

namespace App\Services\Orientation;

use Illuminate\Support\Collection;

/**
 * Readable case summary ("voie A"): deterministic sentences built from structured data only.
 * Each question may carry a translatable `summary_template` (":answer" placeholder); otherwise a generic
 * "question — answer" sentence is used. Nothing is added that is not in the assessment or the engine result.
 */
class CaseSummaryBuilder
{
    /**
     * @param  string[]  $areas  body part names (translated)
     * @param  Collection<int, array{template: ?string, question: string, answer: string}>  $answers
     * @param  string[]  $specialties  recommended first
     * @param  string[]  $conditions
     * @return array{paragraphs: string[], text: string, generated_by: string}
     */
    public function build(array $areas, Collection $answers, bool $emergency, array $specialties, array $conditions, string $locale): array
    {
        $t = fn (string $key, array $replace = []) => __("summary.$key", $replace, $locale);

        $facts = [$t('areas', ['areas' => implode(', ', $areas)])];
        foreach ($answers as $a) {
            if ($a['answer'] === '') {
                continue;
            }
            $facts[] = $a['template']
                ? self::sentence(str_replace(':answer', $a['answer'], $a['template']))
                : $t('qa', ['question' => rtrim($a['question'], ' ?؟:'), 'answer' => $a['answer']]);
        }

        $orientation = [$emergency ? $t('emergency') : $t('no_emergency')];
        if (! $emergency) {
            if ($specialties) {
                $orientation[] = $t('specialty', ['specialty' => $specialties[0]]);
                if (count($specialties) > 1) {
                    $orientation[] = $t('specialty_also', ['specialties' => implode(', ', array_slice($specialties, 1, 3))]);
                }
            }
            $orientation[] = $conditions
                ? $t('conditions', ['conditions' => implode(', ', $conditions)])
                : $t('no_conditions');
        }

        $paragraphs = [implode(' ', $facts), implode(' ', $orientation), __('orientation.disclaimer', [], $locale)];

        return ['paragraphs' => $paragraphs, 'text' => implode("\n\n", $paragraphs), 'generated_by' => 'templates'];
    }

    private static function sentence(string $s): string
    {
        $s = trim($s);

        return preg_match('/[.!?؟]$/u', $s) ? $s : $s.'.';
    }
}
