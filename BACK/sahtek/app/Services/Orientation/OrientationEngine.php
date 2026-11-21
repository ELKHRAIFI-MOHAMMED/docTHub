<?php

namespace App\Services\Orientation;

use App\Models\AssessmentAlert;
use App\Models\AssessmentResult;
use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\Specialty;
use App\Models\SymptomAssessment;
use App\Models\SystemSetting;
use App\Services\Knowledge\Release\KnowledgeVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rule-based medical orientation.
 *
 * Order of evaluation (safety first):
 *  1. Emergency rules (highest priority first). Any match interrupts the normal flow:
 *     only the emergency instruction is returned, no conditions / doctors.
 *  2. Possible conditions, scored from: selected body parts (×1), reported symptoms (×2), matched disease rules (×3).
 *  3. Recommended specialties from conditions + specialty rules; fallback specialty if nothing matched.
 *  4. Guidelines for the top conditions + advice rules.
 *
 * Results are orientation only — never a diagnosis.
 */
class OrientationEngine
{
    private const BODY_PART_FACTOR = 1;
    private const SYMPTOM_FACTOR = 2;
    private const RULE_FACTOR = 3;

    public function __construct(
        private readonly RuleEvaluator $evaluator,
        private readonly ContentGate $gate,
        private readonly ContentVersion $versions,
    ) {
    }

    /** True as soon as the answers given so far match an emergency rule. */
    public function hasEmergency(SymptomAssessment $assessment): bool
    {
        return $this->matchingEmergencyRules(AnswerContext::fromAssessment($assessment))->isNotEmpty();
    }

    private function matchingEmergencyRules(AnswerContext $ctx): Collection
    {
        return OrientationRule::query()
            ->with('translations')
            ->where('result_type', 'emergency')
            ->whereIn('validation_status', $this->gate->emergencyStatuses())
            ->orderByDesc('priority')
            ->get()
            ->filter(fn (OrientationRule $rule) => $this->evaluator->matches($rule, $ctx))
            ->values();
    }

    public function evaluate(SymptomAssessment $assessment): SymptomAssessment
    {
        $ctx = AnswerContext::fromAssessment($assessment);
        $usesUnreviewed = false;

        // 1. Emergency rules.
        $emergencyRules = $this->matchingEmergencyRules($ctx);

        $alerts = [];
        if ($emergencyRules->isNotEmpty()) {
            $country = $assessment->country;
            foreach ($emergencyRules as $rule) {
                $usesUnreviewed = $usesUnreviewed || ! $rule->isReviewed();
                $alerts[] = [
                    'rule_id' => $rule->id,
                    'severity' => $rule->emergency_severity ?? 'critical',
                    'message' => $rule->translate('message', $assessment->locale)
                        ?: __('orientation.emergency_default', [], $assessment->locale),
                    'emergency_number' => $country?->emergency_number,
                ];
            }

            return $this->persist($assessment, $alerts, [], $usesUnreviewed);
        }

        // 2. Possible conditions.
        $usable = $this->gate->usableStatuses();
        $rules = OrientationRule::query()
            ->whereIn('result_type', ['disease', 'specialty', 'advice'])
            ->whereIn('validation_status', $usable)
            ->orderByDesc('priority')
            ->get()
            ->filter(fn (OrientationRule $rule) => $this->evaluator->matches($rule, $ctx));

        /** @var array<int, array{score: float, rules: int[]}> $diseaseScores */
        $diseaseScores = [];
        $add = function (int $diseaseId, float $points, ?int $ruleId = null) use (&$diseaseScores) {
            $diseaseScores[$diseaseId] ??= ['score' => 0.0, 'rules' => []];
            $diseaseScores[$diseaseId]['score'] += $points;
            if ($ruleId) {
                $diseaseScores[$diseaseId]['rules'][] = $ruleId;
            }
        };

        if ($ctx->bodyPartIds) {
            foreach (DB::table('disease_body_parts')->whereIn('body_part_id', $ctx->bodyPartIds)->get() as $row) {
                $add((int) $row->disease_id, self::BODY_PART_FACTOR * $row->weight);
            }
        }
        foreach ($rules->where('result_type', 'disease') as $rule) {
            if ($rule->disease_id) {
                $add((int) $rule->disease_id, self::RULE_FACTOR * max(1, $rule->weight), $rule->id);
            }
        }
        // Reported symptoms only reinforce candidates (selected body part or matched rule):
        // a generic symptom such as fever must not, alone, suggest conditions elsewhere in the body.
        if ($ctx->symptomIds && $diseaseScores) {
            $rows = DB::table('disease_symptoms')
                ->whereIn('symptom_id', $ctx->symptomIds)
                ->whereIn('disease_id', array_keys($diseaseScores))
                ->get();
            foreach ($rows as $row) {
                $add((int) $row->disease_id, self::SYMPTOM_FACTOR * $row->weight);
            }
        }

        $diseases = Disease::published()
            ->usableForOrientation()
            ->whereIn('id', array_keys($diseaseScores))
            ->get()
            ->keyBy('id');

        $conditions = collect($diseaseScores)
            ->filter(fn ($_, $id) => $diseases->has($id))
            ->sortByDesc('score')
            ->take((int) config('docthub.max_conditions', 5));

        foreach ($conditions->keys() as $id) {
            $usesUnreviewed = $usesUnreviewed || ! $diseases[$id]->isReviewed();
        }

        // 3. Specialties.
        $specialtyScores = [];
        foreach ($conditions as $diseaseId => $data) {
            $specialtyId = $diseases[$diseaseId]->specialty_id;
            if ($specialtyId) {
                $specialtyScores[$specialtyId] = ($specialtyScores[$specialtyId] ?? 0) + $data['score'];
            }
        }
        foreach ($rules->where('result_type', 'specialty') as $rule) {
            if ($rule->specialty_id) {
                $specialtyScores[$rule->specialty_id] = ($specialtyScores[$rule->specialty_id] ?? 0) + self::RULE_FACTOR * max(1, $rule->weight);
                $usesUnreviewed = $usesUnreviewed || ! $rule->isReviewed();
            }
        }
        foreach ($rules->where('result_type', 'disease') as $rule) {
            $usesUnreviewed = $usesUnreviewed || ($conditions->has($rule->disease_id) && ! $rule->isReviewed());
        }

        $publishedSpecialtyIds = Specialty::published()->whereIn('id', array_keys($specialtyScores))->pluck('id')->all();
        $specialtyScores = array_intersect_key($specialtyScores, array_flip($publishedSpecialtyIds));
        arsort($specialtyScores);

        if (! $specialtyScores) {
            $fallbackCode = SystemSetting::get('orientation.fallback_specialty_code', 'general-practice');
            $fallback = Specialty::published()->where('code', $fallbackCode)->first();
            if ($fallback) {
                $specialtyScores[$fallback->id] = 0;
            }
        }

        // 4. Guidelines.
        $topDiseaseIds = $conditions->keys()->take(3)->all();
        $adviceGuidelineIds = $rules->where('result_type', 'advice')->pluck('guideline_id')->filter()->all();
        $guidelines = MedicalGuideline::published()
            ->usableForOrientation()
            ->where('guideline_type', '!=', 'emergency')
            ->where(fn ($q) => $q->whereIn('disease_id', $topDiseaseIds)->orWhereIn('id', $adviceGuidelineIds))
            ->orderByRaw("FIELD(guideline_type, 'when_to_consult', 'self_care', 'prevention')")
            ->get();
        foreach ($guidelines as $g) {
            $usesUnreviewed = $usesUnreviewed || ! $g->isReviewed();
        }

        $results = [];
        $rank = 1;
        foreach ($conditions as $diseaseId => $data) {
            $results[] = ['result_type' => 'condition', 'disease_id' => $diseaseId, 'score' => $data['score'], 'rank' => $rank++, 'matched_rule_ids' => array_values(array_unique($data['rules']))];
        }
        $rank = 1;
        foreach ($specialtyScores as $specialtyId => $score) {
            $results[] = ['result_type' => 'specialty', 'specialty_id' => $specialtyId, 'score' => $score, 'rank' => $rank++, 'matched_rule_ids' => []];
        }
        $rank = 1;
        foreach ($guidelines as $g) {
            $results[] = ['result_type' => 'guideline', 'guideline_id' => $g->id, 'score' => 0, 'rank' => $rank++, 'matched_rule_ids' => []];
        }

        return $this->persist($assessment, [], $results, $usesUnreviewed);
    }

    private function persist(SymptomAssessment $assessment, array $alerts, array $results, bool $usesUnreviewed): SymptomAssessment
    {
        DB::transaction(function () use ($assessment, $alerts, $results, $usesUnreviewed) {
            $assessment->results()->delete();
            $assessment->alerts()->delete();

            foreach ($alerts as $alert) {
                AssessmentAlert::create($alert + ['assessment_id' => $assessment->id]);
            }
            foreach ($results as $result) {
                AssessmentResult::create($result + ['assessment_id' => $assessment->id]);
            }

            $assessment->forceFill([
                'status' => 'completed',
                'evaluated_at' => now(),
                'engine_version' => config('docthub.engine_version'),
                'questionnaire_version' => $this->versions->questionnaire(),
                'ruleset_version' => $this->versions->ruleset(),
                // Results depend on the content at evaluation time: the knowledge version is refreshed here.
                'knowledge_version' => app(KnowledgeVersion::class)->current(),
                'uses_unreviewed_content' => $usesUnreviewed,
            ])->save();
        });

        return $assessment->refresh();
    }
}
