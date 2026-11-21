<?php

namespace App\Services\Knowledge\Validation;

use App\Models\BodyPart;
use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\Specialty;
use App\Models\Symptom;
use App\Models\SymptomQuestion;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Quality checks on the medical knowledge base (knowledge:validate). Read-only: reports, never fixes.
 *
 * Errors (exit code 1):
 *  - broken_link        a rule / link / guideline / option points to a missing or deleted item
 *  - approved_incomplete an approved item lacks EN/FR/AR text, a source, a reviewer or a version
 *  - duplicate_code     the same code twice in a table, or the same external identity in two tables
 *  - invalid_value      severity outside low/moderate/high (or missing on an approved condition), priority outside 0..100
 *  - disease_incomplete an approved or published condition without body part, symptom or specialty
 *  - emergency_reviewer an active emergency rule without a named reviewer
 * Warnings: disease_incomplete for drafts (imported items are unlinked on purpose until a reviewer links them).
 */
class KnowledgeValidator
{
    public const SEVERITIES = ['low', 'moderate', 'high']; // faible / moyenne / élevée

    public const PRIORITY_MIN = 0;

    public const PRIORITY_MAX = 100;

    /** @var Issue[] */
    private array $issues = [];

    /** @return Issue[] */
    public function run(): array
    {
        $this->issues = [];
        $this->brokenLinks();
        $this->approvedItems();
        $this->duplicateCodes();
        $this->invalidValues();
        $this->incompleteDiseases();
        $this->emergencyReviewers();

        return $this->issues;
    }

    private function add(string $level, string $check, string $item, string $message): void
    {
        $this->issues[] = new Issue($level, $check, $item, $message);
    }

    private static function label(Model $m): string
    {
        return $m->getMorphClass().':'.($m->getAttribute('code') ?? '#'.$m->getKey());
    }

    /** Ids of rows that exist and are not soft-deleted. */
    private static function liveIds(string $class): array
    {
        return array_flip($class::query()->pluck('id')->all());
    }

    private function brokenLinks(): void
    {
        $live = [
            'disease' => self::liveIds(Disease::class),
            'specialty' => self::liveIds(Specialty::class),
            'symptom' => self::liveIds(Symptom::class),
            'body_part' => self::liveIds(BodyPart::class),
            'guideline' => self::liveIds(MedicalGuideline::class),
            'question' => self::liveIds(SymptomQuestion::class),
        ];
        $questionCodes = array_flip(SymptomQuestion::query()->pluck('code')->all());
        $check = function (Model $owner, string $kind, ?int $id, string $what) use ($live) {
            if ($id !== null && ! isset($live[$kind][$id])) {
                $this->add('error', 'broken_link', self::label($owner), "$what points to a missing or deleted $kind (#$id)");
            }
        };

        // Rules: every reference, the target required by the result type, and question codes inside the clauses.
        $targets = ['disease' => 'disease_id', 'specialty' => 'specialty_id', 'advice' => 'guideline_id'];
        foreach (OrientationRule::query()->whereNotIn('validation_status', ['rejected', 'retired'])->get() as $rule) {
            $check($rule, 'disease', $rule->disease_id, 'disease_id');
            $check($rule, 'specialty', $rule->specialty_id, 'specialty_id');
            $check($rule, 'guideline', $rule->guideline_id, 'guideline_id');
            $check($rule, 'body_part', $rule->body_part_id, 'body_part_id');
            $check($rule, 'question', $rule->question_id, 'question_id');
            if (isset($targets[$rule->result_type]) && $rule->{$targets[$rule->result_type]} === null) {
                $this->add('error', 'broken_link', self::label($rule), "{$rule->result_type} rule without {$targets[$rule->result_type]}");
            }
            foreach ((array) $rule->conditions as $i => $clause) {
                if (isset($clause['question']) && ! isset($questionCodes[$clause['question']])) {
                    $this->add('error', 'broken_link', self::label($rule), "condition #$i uses unknown question code \"{$clause['question']}\"");
                }
            }
        }

        // Link tables of live conditions.
        $diseaseCodes = Disease::query()->pluck('code', 'id');
        foreach (DB::table('disease_symptoms')->whereIn('disease_id', $diseaseCodes->keys())->get() as $row) {
            if (! isset($live['symptom'][$row->symptom_id])) {
                $this->add('error', 'broken_link', "disease:{$diseaseCodes[$row->disease_id]}", "linked to a missing or deleted symptom (#{$row->symptom_id})");
            }
        }
        foreach (DB::table('disease_body_parts')->whereIn('disease_id', $diseaseCodes->keys())->get() as $row) {
            if (! isset($live['body_part'][$row->body_part_id])) {
                $this->add('error', 'broken_link', "disease:{$diseaseCodes[$row->disease_id]}", "linked to a missing or deleted body part (#{$row->body_part_id})");
            }
        }
        foreach (Disease::query()->whereNotNull('specialty_id')->get() as $disease) {
            $check($disease, 'specialty', $disease->specialty_id, 'specialty_id');
        }
        foreach (MedicalGuideline::query()->get() as $guideline) {
            $check($guideline, 'disease', $guideline->disease_id, 'disease_id');
            $check($guideline, 'specialty', $guideline->specialty_id, 'specialty_id');
        }
        foreach (DB::table('question_options')->whereNotNull('symptom_id')->get() as $option) {
            if (! isset($live['symptom'][$option->symptom_id])) {
                $this->add('error', 'broken_link', "question_option:{$option->code}", "points to a missing or deleted symptom (#{$option->symptom_id})");
            }
        }
        foreach (DB::table('knowledge_aliases')->get() as $alias) {
            if (! isset($live[$alias->aliasable_type][$alias->aliasable_id])) {
                $this->add('error', 'broken_link', "alias:{$alias->alias}", "points to a missing or deleted {$alias->aliasable_type} (#{$alias->aliasable_id})");
            }
        }
    }

    private function approvedItems(): void
    {
        $sets = [
            [Disease::query()->where('review_status', 'approved')->get(), ['name', 'description']],
            [MedicalGuideline::query()->where('review_status', 'approved')->get(), ['title', 'content']],
            [OrientationRule::query()->where('validation_status', 'approved')->get(), ['message']],
        ];

        foreach ($sets as [$items, $fields]) {
            foreach ($items as $item) {
                $label = self::label($item);
                $translated = Translation::where('translatable_type', $item->getMorphClass())->where('translatable_id', $item->getKey())
                    ->where('value', '!=', '')->get()->groupBy(fn ($t) => $t->locale.'.'.$t->field);
                foreach ($fields as $field) {
                    if (blank($item->getAttribute($field))) {
                        if ($field !== 'description' && $field !== 'message') {
                            $this->add('error', 'approved_incomplete', $label, "approved without English $field");
                        }

                        continue;
                    }
                    foreach (['fr', 'ar'] as $locale) {
                        if (! $translated->has("$locale.$field")) {
                            $this->add('error', 'approved_incomplete', $label, "approved without $locale translation of $field");
                        }
                    }
                }
                if (blank($item->getAttribute('source_name')) && ! $item->getAttribute('source_document_id')) {
                    $this->add('error', 'approved_incomplete', $label, 'approved without a source');
                }
                if (blank($item->getAttribute('reviewed_by_name'))) {
                    $this->add('error', 'approved_incomplete', $label, 'approved without a reviewer');
                }
                if ((int) $item->getAttribute('version') < 1) {
                    $this->add('error', 'approved_incomplete', $label, 'approved without a version');
                }
            }
        }
    }

    private function duplicateCodes(): void
    {
        $tables = ['specialties', 'body_parts', 'symptoms', 'diseases', 'symptom_questions', 'orientation_rules'];
        foreach ($tables as $table) {
            $dupes = DB::table($table)->selectRaw('LOWER(TRIM(code)) AS c, COUNT(*) AS n')->groupBy('c')->having('n', '>', 1)->get();
            foreach ($dupes as $d) {
                $this->add('error', 'duplicate_code', "$table:{$d->c}", "code used {$d->n} times");
            }
        }

        $symptoms = DB::table('symptoms')->whereNotNull('external_system')->whereNull('deleted_at')->get(['external_system', 'external_code'])
            ->map(fn ($r) => "{$r->external_system}:{$r->external_code}")->all();
        $both = DB::table('diseases')->whereNotNull('external_system')->whereNull('deleted_at')->get(['external_system', 'external_code'])
            ->map(fn ($r) => "{$r->external_system}:{$r->external_code}")->intersect($symptoms);
        foreach ($both as $identity) {
            $this->add('error', 'duplicate_code', $identity, 'same external item imported both as a symptom and as a condition');
        }
    }

    private function invalidValues(): void
    {
        foreach (Disease::query()->get(['id', 'code', 'severity', 'review_status']) as $disease) {
            if ($disease->severity !== null && ! in_array($disease->severity, self::SEVERITIES, true)) {
                $this->add('error', 'invalid_value', self::label($disease), "severity \"{$disease->severity}\" is not one of ".implode(', ', self::SEVERITIES));
            } elseif ($disease->severity === null && $disease->review_status === 'approved') {
                $this->add('error', 'invalid_value', self::label($disease), 'approved without a severity');
            }
        }
        $outOfRange = OrientationRule::query()->where(fn ($q) => $q->where('priority', '<', self::PRIORITY_MIN)->orWhere('priority', '>', self::PRIORITY_MAX))->get();
        foreach ($outOfRange as $rule) {
            $this->add('error', 'invalid_value', self::label($rule), "priority {$rule->priority} outside ".self::PRIORITY_MIN.'..'.self::PRIORITY_MAX);
        }
    }

    private function incompleteDiseases(): void
    {
        $diseases = Disease::query()->withCount(['bodyParts', 'symptoms'])->get();
        foreach ($diseases as $disease) {
            $missing = array_keys(array_filter([
                'body part' => $disease->body_parts_count === 0,
                'symptom' => $disease->symptoms_count === 0,
                'specialty' => $disease->specialty_id === null,
            ]));
            if (! $missing) {
                continue;
            }
            $blocking = $disease->review_status === 'approved' || $disease->status === 'published';
            $this->add($blocking ? 'error' : 'warning', 'disease_incomplete', self::label($disease), 'no '.implode(', no ', $missing).($blocking ? '' : ' (draft: to be linked by a reviewer)'));
        }
    }

    private function emergencyReviewers(): void
    {
        $rules = OrientationRule::query()->where('result_type', 'emergency')->whereNotIn('validation_status', ['rejected', 'retired'])->get();
        foreach ($rules as $rule) {
            if (blank($rule->reviewed_by_name)) {
                $this->add('error', 'emergency_reviewer', self::label($rule), "active emergency rule ({$rule->validation_status}) without a named reviewer");
            }
        }
    }
}
