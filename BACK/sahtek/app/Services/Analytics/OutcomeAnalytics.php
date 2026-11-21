<?php

namespace App\Services\Analytics;

use App\Models\AssessmentAlert;
use App\Models\AssessmentOutcome;
use App\Models\AssessmentResult;
use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\Specialty;
use App\Models\SymptomAssessment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Quality indicators built from assessments and post-consultation outcomes (ground truth).
 * Aggregates only: no indicator exposes an individual.
 */
class OutcomeAnalytics
{
    public function indicators(CarbonInterface $since): array
    {
        $assessments = SymptomAssessment::where('created_at', '>=', $since);
        $total = (clone $assessments)->count();
        $completedIds = (clone $assessments)->where('status', 'completed')->pluck('id');
        $completed = $completedIds->count();

        $emergencyIds = AssessmentAlert::whereIn('assessment_id', $completedIds)->distinct()->pluck('assessment_id');
        $outcomes = AssessmentOutcome::whereIn('assessment_id', $completedIds)->whereNotNull('collected_at')->get();

        // Concordance: recommended specialty (rank 1) == specialty actually consulted.
        $top = AssessmentResult::whereIn('assessment_id', $outcomes->pluck('assessment_id'))
            ->where('result_type', 'specialty')->where('rank', 1)->pluck('specialty_id', 'assessment_id');
        $withSpecialty = $outcomes->filter(fn ($o) => $o->consulted && $o->consulted_specialty_id && isset($top[$o->assessment_id]));
        $matches = $withSpecialty->filter(fn ($o) => (int) $top[$o->assessment_id] === (int) $o->consulted_specialty_id);

        $bySpecialty = $withSpecialty->groupBy(fn ($o) => $top[$o->assessment_id])->map(fn ($group, $specialtyId) => [
            'specialty_id' => (int) $specialtyId,
            'outcomes' => $group->count(),
            'confirmed' => $group->filter(fn ($o) => (int) $o->consulted_specialty_id === (int) $specialtyId)->count(),
        ]);
        $names = Specialty::withTrashed()->whereIn('id', $bySpecialty->keys())->pluck('name', 'id');

        // Did the conditions shown include the diagnosis the doctor gave?
        $withDiagnosis = $outcomes->whereNotNull('diagnosis_disease_id');
        $conditionHits = $withDiagnosis->filter(fn ($o) => AssessmentResult::where('assessment_id', $o->assessment_id)
            ->where('result_type', 'condition')->where('disease_id', $o->diagnosis_disease_id)->exists());

        $emergencyOutcomes = $outcomes->whereIn('assessment_id', $emergencyIds)->whereNotNull('went_to_emergency');

        $byRule = AssessmentAlert::whereIn('assessment_id', $completedIds)
            ->join('orientation_rules', 'orientation_rules.id', '=', 'assessment_alerts.rule_id')
            ->selectRaw('orientation_rules.code, COUNT(*) as total')
            ->groupBy('orientation_rules.code')->orderByDesc('total')->limit(10)
            ->pluck('total', 'code');

        $ratio = fn (int $n, int $d) => $d > 0 ? round($n / $d, 3) : null;

        return [
            'since' => $since->toDateString(),
            'assessments' => ['total' => $total, 'completed' => $completed, 'completion_rate' => $ratio($completed, $total)],
            'emergency' => [
                'count' => $emergencyIds->count(),
                'rate' => $ratio($emergencyIds->count(), $completed),
                'by_rule' => $byRule,
                'followed_to_emergency' => $ratio($emergencyOutcomes->where('went_to_emergency', true)->count(), $emergencyOutcomes->count()),
            ],
            'outcomes' => [
                'collected' => $outcomes->count(),
                'response_rate' => $ratio($outcomes->count(), $completed),
                'helpfulness_avg' => $outcomes->whereNotNull('helpfulness')->avg('helpfulness') !== null
                    ? round($outcomes->whereNotNull('helpfulness')->avg('helpfulness'), 2) : null,
            ],
            'concordance' => [
                'specialty' => $ratio($matches->count(), $withSpecialty->count()),
                'sample_size' => $withSpecialty->count(),
                'by_recommended_specialty' => $bySpecialty->values()->map(fn ($r) => $r + [
                    'specialty' => $names[$r['specialty_id']] ?? '—',
                    'rate' => $ratio($r['confirmed'], $r['outcomes']),
                ]),
                'diagnosis_in_conditions' => $ratio($conditionHits->count(), $withDiagnosis->count()),
                'diagnosis_sample_size' => $withDiagnosis->count(),
            ],
            'content' => $this->contentCoverage(),
            'versions' => (clone $assessments)->where('status', 'completed')
                ->selectRaw('ruleset_version, COUNT(*) as total')->groupBy('ruleset_version')->pluck('total', 'ruleset_version'),
            // Completed assessments per knowledge release (X.Y, "unreleased", "unreviewed"; null = before releases existed).
            'knowledge_versions' => (clone $assessments)->where('status', 'completed')
                ->selectRaw("COALESCE(knowledge_version, 'none') AS kv, COUNT(*) as total")->groupBy('kv')->pluck('total', 'kv'),
        ];
    }

    /** Share of medical content approved by a reviewer, and share of approved content linked to a source document. */
    public function contentCoverage(): array
    {
        $row = function (string $model, string $column) {
            $total = $model::count();
            $approved = $model::where($column, 'approved')->count();
            $traced = $model::where($column, 'approved')->whereNotNull('source_document_id')->count();

            return ['total' => $total, 'approved' => $approved, 'approved_with_source_document' => $traced];
        };

        return [
            'rules' => $row(OrientationRule::class, 'validation_status'),
            'guidelines' => $row(MedicalGuideline::class, 'review_status'),
            'diseases' => $row(Disease::class, 'review_status'),
        ];
    }

    /**
     * Pseudonymised record-level export. Only assessments of patients whose health-data consent is active.
     * No name, e-mail, phone, exact date of birth, internal id or free text.
     *
     * @return \Generator<array>
     */
    public function pseudonymisedRows(CarbonInterface $since): \Generator
    {
        $consented = DB::table('patient_consents')->where('consent_type', 'health_data')->whereNull('revoked_at')->pluck('patient_id');

        $query = SymptomAssessment::query()
            ->where('status', 'completed')->where('created_at', '>=', $since)
            ->whereIn('patient_id', $consented)
            ->with(['answers.question:id,code', 'results.specialty:id,code', 'results.disease:id,code', 'alerts', 'outcome', 'country:id,iso2', 'city:id,name'])
            ->orderBy('id');

        foreach ($query->lazy(200) as $a) {
            $answers = [];
            foreach ($a->answers as $ans) {
                if ($ans->question) {
                    $answers[$ans->question->code] = $ans->option_codes ?: $ans->value;
                }
            }
            $o = $a->outcome;
            yield [
                'subject' => substr(hash_hmac('sha256', 'patient:'.$a->patient_id, (string) config('app.key')), 0, 16),
                'week' => $a->created_at->startOfWeek()->toDateString(),
                'age_band' => $a->age_years !== null ? (intdiv($a->age_years, 10) * 10).'-'.(intdiv($a->age_years, 10) * 10 + 9) : null,
                'sex' => $a->sex,
                'country' => $a->country?->iso2,
                'city' => $a->city?->name,
                'body_part_ids' => $a->body_part_ids,
                'answers' => $answers,
                'emergency' => $a->alerts->isNotEmpty(),
                'recommended_specialty' => $a->results->firstWhere('result_type', 'specialty')?->specialty?->code,
                'conditions' => $a->results->where('result_type', 'condition')->map(fn ($r) => $r->disease?->code)->filter()->values(),
                'outcome' => $o && $o->collected_at ? [
                    'consulted' => $o->consulted,
                    'consulted_specialty_id' => $o->consulted_specialty_id,
                    'diagnosis_disease_id' => $o->diagnosis_disease_id,
                    'diagnosis_icd11' => $o->diagnosis_icd11,
                    'went_to_emergency' => $o->went_to_emergency,
                    'helpfulness' => $o->helpfulness,
                ] : null,
                'questionnaire_version' => $a->questionnaire_version,
                'ruleset_version' => $a->ruleset_version,
                'knowledge_version' => $a->knowledge_version,
                'engine_version' => $a->engine_version,
            ];
        }
    }
}
