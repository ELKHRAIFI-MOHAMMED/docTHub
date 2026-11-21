<?php

namespace App\Http\Resources;

use App\Models\BodyPart;
use App\Models\SymptomQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full orientation result of an evaluated assessment.
 * `emergency` is non-null when an emergency rule matched; in that case the normal flow is interrupted
 * and `conditions`, `specialties` and `guidelines` are empty on purpose.
 */
class OrientationResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $this->resource->loadMissing([
            'alerts',
            'country',
            'results.disease' => fn ($q) => $q->withLocaleTranslations()->with(['symptoms' => fn ($s) => $s->withLocaleTranslations()]),
            'results.specialty' => fn ($q) => $q->withLocaleTranslations(),
            'results.guideline' => fn ($q) => $q->withLocaleTranslations(),
            'answers',
            'outcome',
        ]);

        $questions = SymptomQuestion::withTrashed()
            ->withLocaleTranslations()
            ->with(['options' => fn ($q) => $q->withLocaleTranslations()])
            ->whereIn('id', $this->answers->pluck('question_id'))
            ->get()->keyBy('id');

        // Same order as the questionnaire: body-part questions first, then general ones.
        $ordered = $this->answers->sortBy(fn ($a) => [$questions->get($a->question_id)?->body_part_id === null ? 1 : 0, $questions->get($a->question_id)?->sort_order ?? 0, $a->question_id])->values();
        $rows = $ordered->map(function ($answer) use ($questions, $locale) {
            $q = $questions->get($answer->question_id);
            if (! $q) {
                return null;
            }
            $labels = $q->options->whereIn('code', $answer->option_codes ?? [])->map(fn ($o) => $o->translate('label'))->values();
            $value = in_array($answer->value, ['yes', 'no'], true) ? __('summary.'.$answer->value, [], $locale) : ($answer->value ?? '');

            return [
                'question' => $q->translate('text'),
                'answer' => $labels->isNotEmpty() ? $labels->implode(', ') : trim($value.' '.($q->unit ?? '')),
                'template' => $q->translate('summary_template'),
            ];
        })->filter()->values();
        $summary = $rows->map(fn ($r) => ['question' => $r['question'], 'answer' => $r['answer']]);

        $alerts = $this->alerts;
        $country = $this->country;
        // Render emergency messages in the viewer's language (the stored message is kept as an audit copy).
        $rules = \App\Models\OrientationRule::withTrashed()->withLocaleTranslations()->whereIn('id', $alerts->pluck('rule_id')->filter())->get()->keyBy('id');
        $messages = $alerts->map(fn ($a) => ($a->rule_id && $rules->has($a->rule_id) ? $rules[$a->rule_id]->translate('message') : null)
            ?: __('orientation.emergency_default', [], $locale));

        return [
            'id' => $this->uuid,
            'status' => $this->status,
            'evaluated_at' => $this->evaluated_at?->toIso8601String(),
            'engine_version' => $this->engine_version,
            'body_parts' => BodyPartResource::collection(
                BodyPart::withTrashed()->withLocaleTranslations()->whereIn('id', $this->body_part_ids ?? [])->get()
            ),
            'summary' => $summary,
            'case_summary' => app(\App\Services\Orientation\CaseSummaryBuilder::class)->build(
                BodyPart::withTrashed()->withLocaleTranslations()->whereIn('id', $this->body_part_ids ?? [])->get()->map(fn ($b) => $b->translate('name'))->all(),
                $rows,
                $alerts->isNotEmpty(),
                $this->results->where('result_type', 'specialty')->filter(fn ($r) => $r->specialty)->map(fn ($r) => $r->specialty->translate('name'))->values()->all(),
                $this->results->where('result_type', 'condition')->filter(fn ($r) => $r->disease)->map(fn ($r) => $r->disease->translate('name'))->values()->all(),
                $locale,
            ),
            'follow_up' => [
                'collected' => (bool) $this->outcome?->collected_at,
                'available' => $this->isCompleted(),
            ],
            'emergency' => $alerts->isEmpty() ? null : [
                'severity' => $alerts->contains('severity', 'critical') ? 'critical' : 'urgent',
                'messages' => $messages->unique()->values(),
                'emergency_number' => $alerts->first()->emergency_number,
                'emergency_number_verified' => (bool) $country?->emergency_number_verified,
                'country' => $country ? $country->translate('name') : null,
            ],
            'conditions' => $this->results->where('result_type', 'condition')->filter(fn ($r) => $r->disease)->map(fn ($r) => [
                'disease' => new DiseaseResource($r->disease),
                'rank' => $r->rank,
                'match_strength' => $r->score >= 6 ? 'strong' : ($r->score >= 3 ? 'moderate' : 'weak'),
            ])->values(),
            'specialties' => $this->results->where('result_type', 'specialty')->filter(fn ($r) => $r->specialty)
                ->map(fn ($r) => new SpecialtyResource($r->specialty))->values(),
            'guidelines' => $this->results->where('result_type', 'guideline')->filter(fn ($r) => $r->guideline)
                ->map(fn ($r) => new GuidelineResource($r->guideline))->values(),
            'uses_unreviewed_content' => (bool) $this->uses_unreviewed_content,
            'disclaimer' => __('orientation.disclaimer', [], $locale),
            'unreviewed_notice' => $this->uses_unreviewed_content ? __('orientation.unreviewed', [], $locale) : null,
        ];
    }
}
