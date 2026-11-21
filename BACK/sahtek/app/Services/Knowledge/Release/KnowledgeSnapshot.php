<?php

namespace App\Services\Knowledge\Release;

use App\Models\BodyPart;
use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\SourceDocument;
use App\Models\Specialty;
use App\Models\Symptom;
use App\Models\SymptomQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Canonical snapshot of the APPROVED medical knowledge (conditions, guidelines, rules) plus the reference items they
 * point to (specialties, body parts, symptoms, questions), which have no review workflow of their own.
 *
 * Deterministic: lists sorted by code (or id), fixed key order, no timestamps of the rows themselves, so the same
 * content always gives the same SHA-256 (content_hash). Used by knowledge:export and to stamp assessments.
 */
class KnowledgeSnapshot
{
    public const HASH_CACHE_KEY = 'docthub.knowledge.snapshot_hash';

    /** @return array{items: array, counts: array<string, int>, content_hash: string} */
    public function build(): array
    {
        $refs = ['specialty' => [], 'body_part' => [], 'symptom' => [], 'question' => []];

        $diseases = Disease::query()->where('review_status', 'approved')
            ->with(['translations', 'specialty:id,code', 'symptoms:id,code', 'bodyParts:id,code', 'sourceDocument.source'])
            ->orderBy('code')->get()
            ->map(function (Disease $d) use (&$refs) {
                $refs['specialty'][] = $d->specialty_id;
                array_push($refs['symptom'], ...$d->symptoms->pluck('id'));
                array_push($refs['body_part'], ...$d->bodyParts->pluck('id'));

                return [
                    'code' => $d->code,
                    'name' => $d->name,
                    'description' => $d->description,
                    'severity' => $d->severity,
                    'specialty' => $d->specialty?->code,
                    'body_parts' => $d->bodyParts->sortBy('code')->map(fn ($b) => ['code' => $b->code, 'weight' => (int) $b->pivot->weight])->values()->all(),
                    'symptoms' => $d->symptoms->sortBy('code')->map(fn ($s) => ['code' => $s->code, 'weight' => (int) $s->pivot->weight])->values()->all(),
                    'external' => self::external($d),
                    'translations' => self::translations($d),
                    'provenance' => self::provenance($d),
                    'review' => self::review($d),
                    'is_sample' => (bool) $d->is_sample,
                ];
            })->values()->all();

        $guidelines = MedicalGuideline::query()->where('review_status', 'approved')
            ->with(['translations', 'disease:id,code', 'specialty:id,code', 'sourceDocument.source'])
            ->orderBy('id')->get()
            ->map(function (MedicalGuideline $g) use (&$refs) {
                $refs['specialty'][] = $g->specialty_id;

                return [
                    'id' => $g->id,
                    'guideline_type' => $g->guideline_type,
                    'title' => $g->title,
                    'content' => $g->content,
                    'disease' => $g->disease?->code,
                    'specialty' => $g->specialty?->code,
                    'translations' => self::translations($g),
                    'provenance' => self::provenance($g),
                    'review' => self::review($g),
                    'is_sample' => (bool) $g->is_sample,
                ];
            })->values()->all();

        $questionCodes = SymptomQuestion::withTrashed()->pluck('id', 'code');
        $rules = OrientationRule::query()->where('validation_status', 'approved')
            ->with(['translations', 'bodyPart:id,code', 'question:id,code', 'disease:id,code', 'specialty:id,code', 'sourceDocument.source'])
            ->orderBy('code')->get()
            ->map(function (OrientationRule $r) use (&$refs, $questionCodes) {
                $refs['specialty'][] = $r->specialty_id;
                $refs['body_part'][] = $r->body_part_id;
                $refs['question'][] = $r->question_id;
                foreach ((array) $r->conditions as $clause) {
                    $refs['question'][] = isset($clause['question']) ? ($questionCodes[$clause['question']] ?? null) : null;
                }

                return [
                    'code' => $r->code,
                    'name' => $r->name,
                    'result_type' => $r->result_type,
                    'priority' => (int) $r->priority,
                    'weight' => (int) $r->weight,
                    'body_part' => $r->bodyPart?->code,
                    'question' => $r->question?->code,
                    'operator' => $r->operator,
                    'expected_value' => $r->expected_value,
                    'conditions' => $r->conditions,
                    'disease' => $r->disease?->code,
                    'specialty' => $r->specialty?->code,
                    'guideline_id' => $r->guideline_id,
                    'emergency_severity' => $r->emergency_severity,
                    'message' => $r->message,
                    'translations' => self::translations($r),
                    'provenance' => self::provenance($r),
                    'review' => self::review($r),
                    'is_sample' => (bool) $r->is_sample,
                ];
            })->values()->all();

        $ids = fn (string $kind) => array_values(array_unique(array_filter($refs[$kind])));
        $plain = fn (Model $m, array $fields) => ['code' => $m->code] + $m->only($fields) + ['status' => $m->status, 'translations' => self::translations($m)];

        $items = [
            'diseases' => $diseases,
            'guidelines' => $guidelines,
            'rules' => $rules,
            // Reference items, exported only because an approved item points to them (no review workflow of their own).
            'specialties' => Specialty::query()->whereKey($ids('specialty'))->with('translations')->orderBy('code')->get()
                ->map(fn ($s) => $plain($s, ['name', 'description']))->values()->all(),
            'body_parts' => BodyPart::query()->whereKey($ids('body_part'))->with('translations')->orderBy('code')->get()
                ->map(fn ($b) => $plain($b, ['name', 'description', 'view']))->values()->all(),
            'symptoms' => Symptom::query()->whereKey($ids('symptom'))->with('translations')->orderBy('code')->get()
                ->map(fn ($s) => $plain($s, ['name', 'description']) + ['external' => self::external($s)])->values()->all(),
            'questions' => SymptomQuestion::query()->whereKey($ids('question'))
                ->with(['translations', 'bodyPart:id,code', 'options.translations', 'options.symptom:id,code', 'conditions.dependsOn:id,code'])
                ->orderBy('code')->get()
                ->map(fn (SymptomQuestion $q) => $plain($q, ['text', 'help_text', 'summary_template', 'type', 'min_value', 'max_value', 'unit', 'is_required']) + [
                    'body_part' => $q->bodyPart?->code,
                    'options' => $q->options->sortBy('code')->map(fn ($o) => [
                        'code' => $o->code, 'label' => $o->label, 'score' => $o->score, 'symptom' => $o->symptom?->code, 'translations' => self::translations($o),
                    ])->values()->all(),
                    'conditions' => $q->conditions->sortBy('id')->map(fn ($c) => [
                        'depends_on' => $c->dependsOn?->code, 'operator' => $c->operator, 'value' => $c->value,
                    ])->values()->all(),
                ])->values()->all(),
        ];

        $counts = array_map('count', $items);
        $hash = hash('sha256', json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Cache::forever(self::HASH_CACHE_KEY, $hash);

        return ['items' => $items, 'counts' => $counts, 'content_hash' => $hash];
    }

    /** Current content hash, cached until a write path calls forgetHash(). */
    public function currentHash(): string
    {
        return Cache::get(self::HASH_CACHE_KEY) ?? $this->build()['content_hash'];
    }

    public static function forgetHash(): void
    {
        Cache::forget(self::HASH_CACHE_KEY);
    }

    /** @return array<string, array<string, string>> locale => field => value, sorted */
    private static function translations(Model $m): array
    {
        $out = [];
        foreach ($m->translations->sortBy(fn ($t) => $t->locale.'.'.$t->field) as $t) {
            $out[$t->locale][$t->field] = $t->value;
        }

        return $out;
    }

    private static function external(Model $m): ?array
    {
        return $m->getAttribute('external_system')
            ? ['system' => $m->external_system, 'code' => $m->external_code, 'uri' => $m->external_uri]
            : null;
    }

    private static function provenance(Model $m): array
    {
        /** @var SourceDocument|null $doc */
        $doc = $m->sourceDocument;

        return [
            'source_name' => $m->getAttribute('source_name'),
            'source_url' => $m->getAttribute('source_url'),
            'source_document' => $doc ? [
                'id' => $doc->id, 'url' => $doc->url, 'language' => $doc->language, 'content_hash' => $doc->content_hash,
                'source' => $doc->source?->code ?? $doc->source?->name, 'license' => $doc->source?->license,
            ] : null,
        ];
    }

    private static function review(Model $m): array
    {
        return [
            'reviewed_by_name' => $m->getAttribute('reviewed_by_name'),
            'reviewed_at' => $m->getAttribute('reviewed_at')?->toIso8601String(),
            'version' => (int) $m->getAttribute('version'),
        ];
    }
}
