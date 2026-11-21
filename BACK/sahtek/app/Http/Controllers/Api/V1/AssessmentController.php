<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Assessment\StoreAssessmentRequest;
use App\Http\Requests\Assessment\SubmitAnswersRequest;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\OrientationResultResource;
use App\Http\Resources\QuestionResource;
use App\Models\AssessmentAnswer;
use App\Models\SymptomAssessment;
use App\Models\SymptomQuestion;
use App\Services\Knowledge\Release\KnowledgeVersion;
use App\Services\Orientation\ContentVersion;
use App\Services\Orientation\OrientationEngine;
use App\Services\Orientation\QuestionFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssessmentController extends ApiController
{
    public function __construct(
        private readonly QuestionFlow $flow,
        private readonly OrientationEngine $engine,
    ) {
    }

    public function store(StoreAssessmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $patient = $this->currentPatient();

        $assessment = SymptomAssessment::create([
            'patient_id' => $patient?->id,
            'country_id' => $data['country_id'] ?? $patient?->country_id,
            'city_id' => $data['city_id'] ?? $patient?->city_id,
            'locale' => app()->getLocale(),
            'sex' => $data['sex'] ?? $patient?->sex,
            'age_years' => $data['age_years'] ?? $patient?->ageYears(),
            'body_part_ids' => array_values(array_unique(array_map('intval', $data['body_part_ids']))),
            'status' => 'in_progress',
            'questionnaire_version' => app(ContentVersion::class)->questionnaire(),
            'knowledge_version' => app(KnowledgeVersion::class)->current(),
        ]);

        return (new AssessmentResource($assessment))->response()->setStatusCode(201);
    }

    public function questions(SymptomAssessment $assessment): JsonResponse
    {
        $this->authorizeAccess($assessment);

        return response()->json(['data' => $this->stateFor($assessment)]);
    }

    public function answers(SubmitAnswersRequest $request, SymptomAssessment $assessment): JsonResponse
    {
        $this->authorizeAccess($assessment);
        abort_if($assessment->isCompleted(), 409, 'This assessment has already been evaluated. Start a new one to change your answers.');

        $candidates = $this->flow->candidateQuestions($assessment)->keyBy('id');
        $rows = [];
        foreach ($request->validated('answers') as $i => $answer) {
            /** @var SymptomQuestion|null $question */
            $question = $candidates->get((int) $answer['question_id']);
            if (! $question) {
                throw ValidationException::withMessages(["answers.$i.question_id" => ['This question does not belong to this assessment.']]);
            }
            $rows[$question->id] = $this->normaliseAnswer($question, $answer, "answers.$i");
        }

        DB::transaction(function () use ($assessment, $rows) {
            foreach ($rows as $questionId => $row) {
                AssessmentAnswer::updateOrCreate(['assessment_id' => $assessment->id, 'question_id' => $questionId], $row);
            }
            // Answers to questions hidden by the new answers are no longer relevant.
            $hidden = $this->flow->hiddenAnsweredQuestionIds($assessment);
            if ($hidden) {
                $assessment->answers()->whereIn('question_id', $hidden)->delete();
            }
        });

        return response()->json(['data' => $this->stateFor($assessment)]);
    }

    public function evaluate(SymptomAssessment $assessment): OrientationResultResource
    {
        $this->authorizeAccess($assessment);

        $state = $this->flow->state($assessment);
        if (! $state['complete'] && ! $this->engine->hasEmergency($assessment)) {
            throw ValidationException::withMessages(['answers' => ['Please answer all required questions first.']]);
        }

        return new OrientationResultResource($this->engine->evaluate($assessment));
    }

    public function results(SymptomAssessment $assessment): OrientationResultResource
    {
        $this->authorizeAccess($assessment);
        abort_unless($assessment->isCompleted(), 409, 'This assessment has not been evaluated yet.');

        return new OrientationResultResource($assessment);
    }

    private function stateFor(SymptomAssessment $assessment): array
    {
        $state = $this->flow->state($assessment);
        QuestionResource::$answers = $state['answers'];

        return [
            'assessment_id' => $assessment->uuid,
            'status' => $assessment->status,
            'questions' => QuestionResource::collection($state['questions'])->resolve(),
            'complete' => $state['complete'],
            'next_question_id' => $state['next_question_id'],
            // Safety first: a warning sign interrupts the questionnaire immediately.
            'emergency_detected' => $this->engine->hasEmergency($assessment),
        ];
    }

    private function normaliseAnswer(SymptomQuestion $question, array $answer, string $key): array
    {
        $codes = array_values(array_unique($answer['option_codes'] ?? []));
        $value = isset($answer['value']) ? trim((string) $answer['value']) : null;
        $fail = fn (string $msg) => throw ValidationException::withMessages([$key => [$msg]]);

        switch ($question->type) {
            case 'single_choice':
            case 'multi_choice':
                $valid = $question->options->pluck('code')->all();
                if (! $codes || array_diff($codes, $valid)) {
                    $fail('Choose one of the proposed answers.');
                }
                if ($question->type === 'single_choice' && count($codes) !== 1) {
                    $fail('Choose exactly one answer.');
                }

                return ['option_codes' => $codes, 'value' => null];

            case 'boolean':
                if (! in_array($value, ['yes', 'no'], true)) {
                    $fail('Answer yes or no.');
                }

                return ['option_codes' => null, 'value' => $value];

            default: // scale, number
                if ($value === null || ! is_numeric($value)) {
                    $fail('Enter a number.');
                }
                $n = (float) $value;
                if (($question->min_value !== null && $n < $question->min_value) || ($question->max_value !== null && $n > $question->max_value)) {
                    $fail("Enter a value between {$question->min_value} and {$question->max_value}.");
                }

                return ['option_codes' => null, 'value' => (string) ($n == (int) $n ? (int) $n : $n)];
        }
    }

    private function authorizeAccess(SymptomAssessment $assessment): void
    {
        Gate::forUser(auth('sanctum')->user())->authorize('access', $assessment);
    }
}
