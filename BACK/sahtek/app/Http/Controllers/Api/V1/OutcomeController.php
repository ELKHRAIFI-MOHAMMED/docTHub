<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Assessment\OutcomeRequest;
use App\Models\AssessmentOutcome;
use App\Models\SymptomAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** "What did the doctor say?" — ground truth used to measure the orientation quality. */
class OutcomeController extends ApiController
{
    public function show(SymptomAssessment $assessment): JsonResponse
    {
        $this->authorizeAccess($assessment);

        return response()->json(['data' => $this->present($assessment->outcome)]);
    }

    public function update(OutcomeRequest $request, SymptomAssessment $assessment): JsonResponse
    {
        $this->authorizeAccess($assessment);
        abort_unless($assessment->isCompleted(), 409, 'This assessment has not been evaluated yet.');

        $data = $request->validated();
        if (! $data['consulted']) {
            $data['consulted_specialty_id'] = null;
            $data['diagnosis_disease_id'] = null;
            $data['diagnosis_icd11'] = null;
        }

        $outcome = AssessmentOutcome::updateOrCreate(
            ['assessment_id' => $assessment->id],
            $data + ['source' => 'patient_reported', 'collected_at' => now()],
        );

        return response()->json(['data' => $this->present($outcome)]);
    }

    private function present(?AssessmentOutcome $o): array
    {
        return [
            'collected' => (bool) $o?->collected_at,
            'consulted' => $o?->consulted,
            'consulted_specialty_id' => $o?->consulted_specialty_id,
            'diagnosis_disease_id' => $o?->diagnosis_disease_id,
            'diagnosis_icd11' => $o?->diagnosis_icd11,
            'went_to_emergency' => $o?->went_to_emergency,
            'helpfulness' => $o?->helpfulness,
            'collected_at' => $o?->collected_at?->toIso8601String(),
        ];
    }

    private function authorizeAccess(SymptomAssessment $assessment): void
    {
        Gate::forUser(auth('sanctum')->user())->authorize('access', $assessment);
    }
}
