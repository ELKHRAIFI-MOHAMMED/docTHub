<?php

namespace Tests\Feature;

use App\Models\BodyPart;
use App\Models\Country;
use App\Models\OrientationRule;
use App\Models\SymptomQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    private function start(array $bodyParts, string $iso = 'MA'): string
    {
        return $this->postJson('/api/v1/assessments', [
            'body_part_ids' => BodyPart::whereIn('code', $bodyParts)->pluck('id')->all(),
            'country_id' => Country::where('iso2', $iso)->value('id'),
        ])->assertCreated()->json('data.id');
    }

    private function answer(string $uuid, array $answers)
    {
        $payload = [];
        foreach ($answers as $code => $value) {
            $q = SymptomQuestion::where('code', $code)->firstOrFail();
            $payload[] = in_array($q->type, ['single_choice', 'multi_choice'], true)
                ? ['question_id' => $q->id, 'option_codes' => (array) $value]
                : ['question_id' => $q->id, 'value' => (string) $value];
        }

        return $this->postJson("/api/v1/assessments/$uuid/answers", ['answers' => $payload]);
    }

    private function chestNormalAnswers(): array
    {
        return [
            'chest_assoc' => ['cough'], 'chest_trigger' => 'on_exertion', 'history_heart' => 'no',
            'pain_intensity' => 5, 'pain_duration' => '1_7d', 'pain_onset' => 'gradual',
            'pain_frequency' => 'intermittent', 'assoc_general' => ['fever'],
        ];
    }

    public function test_assessment_requires_published_body_parts(): void
    {
        $this->postJson('/api/v1/assessments', ['body_part_ids' => []])->assertUnprocessable();
        $this->postJson('/api/v1/assessments', ['body_part_ids' => [999999]])->assertUnprocessable();
    }

    public function test_questions_are_scoped_to_selected_body_parts(): void
    {
        $uuid = $this->start(['chest']);
        $codes = collect($this->getJson("/api/v1/assessments/$uuid/questions")->assertOk()->json('data.questions'))->pluck('code');

        $this->assertTrue($codes->contains('chest_assoc'));
        $this->assertTrue($codes->contains('pain_intensity'));
        $this->assertFalse($codes->contains('head_assoc'));
    }

    public function test_conditional_question_appears_only_when_condition_met(): void
    {
        $uuid = $this->start(['head']);
        $codes = fn () => collect($this->getJson("/api/v1/assessments/$uuid/questions")->json('data.questions'))->pluck('code');

        $this->assertFalse($codes()->contains('head_worst_ever'));
        $this->answer($uuid, ['pain_onset' => 'sudden'])->assertOk();
        $this->assertTrue($codes()->contains('head_worst_ever'));

        // Changing the parent answer hides the question again and drops its stale answer.
        $this->answer($uuid, ['head_worst_ever' => 'no'])->assertOk();
        $this->answer($uuid, ['pain_onset' => 'gradual'])->assertOk();
        $this->assertFalse($codes()->contains('head_worst_ever'));
        $this->assertDatabaseMissing('assessment_answers', ['question_id' => SymptomQuestion::where('code', 'head_worst_ever')->value('id')]);
    }

    public function test_answers_are_validated_against_question_definition(): void
    {
        $uuid = $this->start(['chest']);

        $this->answer($uuid, ['pain_intensity' => 15])->assertUnprocessable();
        $this->answer($uuid, ['pain_duration' => 'forever'])->assertUnprocessable();
        $this->answer($uuid, ['pain_duration' => ['lt_24h', '1_7d']])->assertUnprocessable();
        $this->answer($uuid, ['history_heart' => 'maybe'])->assertUnprocessable();

        // Question from another body part.
        $head = SymptomQuestion::where('code', 'head_assoc')->first();
        $this->postJson("/api/v1/assessments/$uuid/answers", ['answers' => [['question_id' => $head->id, 'option_codes' => ['none']]]])
            ->assertUnprocessable();
    }

    public function test_cannot_evaluate_before_required_questions_are_answered(): void
    {
        $uuid = $this->start(['chest']);
        $this->postJson("/api/v1/assessments/$uuid/evaluate")->assertUnprocessable();
        $this->getJson("/api/v1/assessments/$uuid/results")->assertStatus(409);
    }

    public function test_normal_orientation_returns_conditions_specialty_and_disclaimer(): void
    {
        $this->allowUnreviewedContent();
        $uuid = $this->start(['chest']);
        $this->answer($uuid, $this->chestNormalAnswers())->assertOk()->assertJsonPath('data.complete', true);

        $res = $this->postJson("/api/v1/assessments/$uuid/evaluate")->assertOk();

        $this->assertNull($res->json('data.emergency'));
        $conditions = collect($res->json('data.conditions'))->pluck('disease.code');
        $this->assertContains('angina', $conditions);
        $this->assertContains('bronchitis', $conditions);
        // Fever alone must not suggest conditions of unrelated body parts.
        $this->assertNotContains('pharyngitis', $conditions);
        $this->assertNotContains('gastroenteritis', $conditions);
        $this->assertNotEmpty($res->json('data.specialties'));
        $this->assertNotEmpty($res->json('data.disclaimer'));
        $this->assertTrue($res->json('data.uses_unreviewed_content'));

        $this->getJson("/api/v1/assessments/$uuid/results")->assertOk()->assertJsonPath('data.id', $uuid);
        $this->answer($uuid, ['pain_intensity' => 3])->assertStatus(409);
    }

    public function test_emergency_rule_interrupts_normal_flow_with_country_number(): void
    {
        $this->allowUnreviewedContent();
        $uuid = $this->start(['chest'], 'FR');

        $this->answer($uuid, ['chest_assoc' => ['shortness_of_breath']])
            ->assertOk()
            ->assertJsonPath('data.emergency_detected', true)
            ->assertJsonPath('data.complete', false);

        // Evaluation is allowed immediately despite unanswered questions.
        $res = $this->postJson("/api/v1/assessments/$uuid/evaluate", [], ['X-Locale' => 'fr'])->assertOk();

        $this->assertSame('critical', $res->json('data.emergency.severity'));
        $this->assertSame('112', $res->json('data.emergency.emergency_number'));
        $this->assertStringContainsString('urgence', $res->json('data.emergency.messages.0'));
        $this->assertSame([], $res->json('data.conditions'));
        $this->assertSame([], $res->json('data.specialties'));
        $this->assertDatabaseCount('assessment_alerts', 1);
    }

    public function test_emergency_rules_apply_even_when_unreviewed_content_is_disabled(): void
    {
        $this->allowUnreviewedContent(false);
        $uuid = $this->start(['head']);
        $this->answer($uuid, ['head_assoc' => ['speech_difficulty']])->assertJsonPath('data.emergency_detected', true);
    }

    public function test_rejected_emergency_rule_is_not_applied(): void
    {
        OrientationRule::where('code', 'em-chest-cardiac')->update(['validation_status' => 'rejected']);
        $uuid = $this->start(['chest']);
        $this->answer($uuid, ['chest_assoc' => ['sweating']])->assertJsonPath('data.emergency_detected', false);
    }

    public function test_unreviewed_conditions_are_not_used_in_production_mode(): void
    {
        $this->allowUnreviewedContent(false);
        $uuid = $this->start(['chest']);
        $this->answer($uuid, $this->chestNormalAnswers());

        $res = $this->postJson("/api/v1/assessments/$uuid/evaluate")->assertOk();

        $this->assertSame([], $res->json('data.conditions'));
        $this->assertFalse($res->json('data.uses_unreviewed_content'));
        // Fallback specialty is still recommended so the patient can find a doctor.
        $this->assertSame('general-practice', $res->json('data.specialties.0.code'));
    }

    public function test_patient_assessment_is_private_to_its_owner(): void
    {
        $owner = $this->makePatient();
        $this->actingAsUser($owner);
        $uuid = $this->start(['chest']);

        $this->assertDatabaseHas('symptom_assessments', ['uuid' => $uuid, 'patient_id' => $owner->patient->id]);
        $this->getJson("/api/v1/assessments/$uuid/questions")->assertOk();

        $this->actingAsUser($this->makePatient());
        $this->getJson("/api/v1/assessments/$uuid/questions")->assertForbidden();
    }

    public function test_patient_history_lists_own_assessments(): void
    {
        $this->allowUnreviewedContent();
        $patient = $this->makePatient();
        $this->actingAsUser($patient);
        $uuid = $this->start(['chest']);
        $this->answer($uuid, $this->chestNormalAnswers());
        $this->postJson("/api/v1/assessments/$uuid/evaluate")->assertOk();

        $this->getJson('/api/v1/patient/assessments')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $uuid)
            ->assertJsonPath('data.0.has_emergency_alert', false);
    }
}
