<?php

namespace Tests\Feature;

use App\Models\AssessmentOutcome;
use App\Models\BodyPart;
use App\Models\Country;
use App\Models\KnowledgeSource;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\SourceDocument;
use App\Models\Specialty;
use App\Models\SymptomAssessment;
use App\Models\SymptomQuestion;
use App\Notifications\AssessmentFollowUp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DataCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    private function completedChestAssessment(array $headers = []): string
    {
        $uuid = $this->postJson('/api/v1/assessments', [
            'body_part_ids' => [BodyPart::where('code', 'chest')->value('id')],
            'country_id' => Country::where('iso2', 'MA')->value('id'),
        ], $headers)->json('data.id');

        $answers = [
            'chest_assoc' => ['cough'], 'chest_trigger' => ['on_exertion'], 'history_heart' => 'no', 'pain_intensity' => '5',
            'pain_duration' => ['1_7d'], 'pain_onset' => ['gradual'], 'pain_frequency' => ['intermittent'], 'assoc_general' => ['none'],
        ];
        $payload = [];
        foreach ($answers as $code => $v) {
            $id = SymptomQuestion::where('code', $code)->value('id');
            $payload[] = is_array($v) ? ['question_id' => $id, 'option_codes' => $v] : ['question_id' => $id, 'value' => $v];
        }
        $this->postJson("/api/v1/assessments/$uuid/answers", ['answers' => $payload], $headers)->assertOk();
        $this->postJson("/api/v1/assessments/$uuid/evaluate", [], $headers)->assertOk();

        return $uuid;
    }

    // ---------- Version stamps ----------

    public function test_assessments_are_stamped_with_questionnaire_and_ruleset_versions(): void
    {
        $this->allowUnreviewedContent();
        $uuid = $this->completedChestAssessment();
        $a = SymptomAssessment::where('uuid', $uuid)->first();

        $this->assertMatchesRegularExpression('/^q-[0-9a-f]{10}$/', $a->questionnaire_version);
        $this->assertMatchesRegularExpression('/^r-[0-9a-f]{10}$/', $a->ruleset_version);

        // Changing a rule changes the ruleset fingerprint.
        $before = $a->ruleset_version;
        OrientationRule::where('code', 'dz-angina')->update(['version' => 2]);
        $uuid2 = $this->completedChestAssessment();
        $this->assertNotSame($before, SymptomAssessment::where('uuid', $uuid2)->value('ruleset_version'));
    }

    // ---------- Readable case summary ----------

    public function test_results_include_a_readable_case_summary_built_from_templates(): void
    {
        $this->allowUnreviewedContent();
        $uuid = $this->completedChestAssessment();

        $fr = $this->getJson("/api/v1/assessments/$uuid/results", ['X-Locale' => 'fr'])->assertOk()->json('data.case_summary');
        $this->assertSame('templates', $fr['generated_by']);
        $this->assertStringContainsString('Zones concernées : Poitrine.', $fr['paragraphs'][0]);
        $this->assertStringContainsString('Intensité de la douleur : 5 sur 10.', $fr['paragraphs'][0]);
        $this->assertStringContainsString('Antécédent cardiaque connu : non.', $fr['paragraphs'][0]);
        $this->assertStringContainsString("Aucun signe d'alerte", $fr['paragraphs'][1]);
        $this->assertStringContainsString('Spécialité conseillée : Pneumologie.', $fr['paragraphs'][1]);
        $this->assertStringContainsString('pas un diagnostic', $fr['paragraphs'][2]);

        $ar = $this->getJson("/api/v1/assessments/$uuid/results", ['X-Locale' => 'ar'])->json('data.case_summary.paragraphs.0');
        $this->assertStringContainsString('شدة الألم: 5 من 10.', $ar);
    }

    public function test_question_without_template_uses_generic_sentence(): void
    {
        $this->allowUnreviewedContent();
        SymptomQuestion::where('code', 'pain_onset')->update(['summary_template' => null]);
        $uuid = $this->completedChestAssessment();

        $text = $this->getJson("/api/v1/assessments/$uuid/results")->json('data.case_summary.text');
        $this->assertStringContainsString('How did it start: Gradually.', $text);
    }

    public function test_emergency_summary_does_not_list_conditions(): void
    {
        $uuid = $this->postJson('/api/v1/assessments', ['body_part_ids' => [BodyPart::where('code', 'chest')->value('id')]])->json('data.id');
        $this->postJson("/api/v1/assessments/$uuid/answers", ['answers' => [['question_id' => SymptomQuestion::where('code', 'chest_assoc')->value('id'), 'option_codes' => ['sweating']]]]);
        $summary = $this->postJson("/api/v1/assessments/$uuid/evaluate")->assertOk()->json('data.case_summary.paragraphs.1');

        $this->assertStringContainsString('warning sign was detected', $summary);
        $this->assertStringNotContainsString('Recommended specialty', $summary);
    }

    // ---------- Outcomes (ground truth) ----------

    public function test_outcome_can_be_reported_and_is_validated(): void
    {
        $this->allowUnreviewedContent();
        $uuid = $this->completedChestAssessment();
        $cardio = Specialty::where('code', 'cardiology')->value('id');

        $this->putJson("/api/v1/assessments/$uuid/outcome", ['consulted' => 'maybe'])->assertUnprocessable();
        $this->putJson("/api/v1/assessments/$uuid/outcome", ['consulted' => true, 'diagnosis_icd11' => 'not a code'])->assertUnprocessable();
        $this->putJson("/api/v1/assessments/$uuid/outcome", ['consulted' => true, 'helpfulness' => 9])->assertUnprocessable();

        $this->putJson("/api/v1/assessments/$uuid/outcome", [
            'consulted' => true, 'consulted_specialty_id' => $cardio, 'diagnosis_icd11' => 'BA40.Z', 'went_to_emergency' => false, 'helpfulness' => 4,
        ])->assertOk()->assertJsonPath('data.collected', true)->assertJsonPath('data.consulted_specialty_id', $cardio);

        $this->getJson("/api/v1/assessments/$uuid/results")->assertJsonPath('data.follow_up.collected', true);

        // "Did not consult" clears the clinical fields.
        $this->putJson("/api/v1/assessments/$uuid/outcome", ['consulted' => false, 'consulted_specialty_id' => $cardio])
            ->assertOk()->assertJsonPath('data.consulted_specialty_id', null);
    }

    public function test_outcome_requires_an_evaluated_assessment_and_respects_ownership(): void
    {
        $uuid = $this->postJson('/api/v1/assessments', ['body_part_ids' => [BodyPart::where('code', 'chest')->value('id')]])->json('data.id');
        $this->putJson("/api/v1/assessments/$uuid/outcome", ['consulted' => true])->assertStatus(409);

        $this->allowUnreviewedContent();
        $owner = $this->makePatient();
        $this->actingAsUser($owner);
        $mine = $this->completedChestAssessment();
        $this->actingAsUser($this->makePatient());
        $this->putJson("/api/v1/assessments/$mine/outcome", ['consulted' => true])->assertForbidden();
    }

    public function test_follow_up_is_sent_once_seven_days_after_evaluation(): void
    {
        Notification::fake();
        $this->allowUnreviewedContent();
        $patient = $this->makePatient();
        $patient->patient->consents()->create(['consent_type' => 'health_data', 'version' => '2025-01', 'granted_at' => now()]);
        $this->actingAsUser($patient);
        $uuid = $this->completedChestAssessment();

        $this->artisan('docthub:send-follow-ups')->assertSuccessful();
        Notification::assertNothingSent(); // too early

        SymptomAssessment::where('uuid', $uuid)->update(['evaluated_at' => now()->subDays(8)]);
        $this->artisan('docthub:send-follow-ups')->expectsOutputToContain('sent: 1')->assertSuccessful();
        Notification::assertSentTo($patient, AssessmentFollowUp::class);

        $this->artisan('docthub:send-follow-ups')->expectsOutputToContain('sent: 0');
        $this->assertNotNull(AssessmentOutcome::first()->reminder_sent_at);
    }

    public function test_no_follow_up_without_health_data_consent_or_email_opt_in(): void
    {
        Notification::fake();
        $this->allowUnreviewedContent();
        $patient = $this->makePatient(); // no consent recorded
        $this->actingAsUser($patient);
        $uuid = $this->completedChestAssessment();
        SymptomAssessment::where('uuid', $uuid)->update(['evaluated_at' => now()->subDays(8)]);

        $this->artisan('docthub:send-follow-ups')->expectsOutputToContain('sent: 0');
        Notification::assertNothingSent();
    }

    // ---------- Analytics ----------

    public function test_analytics_concordance_and_pseudonymised_export(): void
    {
        $this->allowUnreviewedContent();
        $pulmo = Specialty::where('code', 'pulmonology')->value('id');
        $cardio = Specialty::where('code', 'cardiology')->value('id');

        $patient = $this->makePatient();
        $patient->patient->consents()->create(['consent_type' => 'health_data', 'version' => '2025-01', 'granted_at' => now()]);
        $this->actingAsUser($patient);
        $a = $this->completedChestAssessment();   // recommended: pulmonology
        $b = $this->completedChestAssessment();
        $this->putJson("/api/v1/assessments/$a/outcome", ['consulted' => true, 'consulted_specialty_id' => $pulmo])->assertOk();
        $this->putJson("/api/v1/assessments/$b/outcome", ['consulted' => true, 'consulted_specialty_id' => $cardio])->assertOk();

        $this->actingAsUser($this->makeAdmin());
        $data = $this->getJson('/api/v1/admin/analytics')->assertOk()->json('data');

        $this->assertSame(2, $data['assessments']['completed']);
        $this->assertEquals(0.5, $data['concordance']['specialty']);
        $this->assertSame(2, $data['concordance']['sample_size']);
        $this->assertSame(2, $data['outcomes']['collected']);
        $this->assertArrayHasKey('rules', $data['content']);

        $export = $this->get('/api/v1/admin/analytics/export')->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", $export)));
        $this->assertCount(2, $lines);
        $row = json_decode($lines[0], true);
        $this->assertSame(16, strlen($row['subject']));
        $this->assertSame('pulmonology', $row['recommended_specialty']);
        $this->assertTrue($row['outcome']['consulted']);
        // No direct identifiers.
        $this->assertStringNotContainsString($patient->email, $export);
        $this->assertStringNotContainsString('Ient', $export);
        $this->assertArrayNotHasKey('patient_id', $row);

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.analytics_exported']);
    }

    public function test_export_excludes_patients_without_active_consent(): void
    {
        $this->allowUnreviewedContent();
        $patient = $this->makePatient();
        $consent = $patient->patient->consents()->create(['consent_type' => 'health_data', 'version' => '2025-01', 'granted_at' => now()]);
        $this->actingAsUser($patient);
        $this->completedChestAssessment();
        $consent->update(['revoked_at' => now()]);

        $this->actingAsUser($this->makeAdmin());
        $this->assertSame('', trim($this->get('/api/v1/admin/analytics/export')->streamedContent()));
    }

    // ---------- Knowledge provenance ----------

    public function test_source_document_fetch_fingerprints_and_sends_changed_content_back_to_review(): void
    {
        Http::fake(['guidelines.example/*' => Http::sequence()
            ->push('<html><body><h1>Chest pain</h1><p>Call emergency services.</p><script>x()</script></body></html>')
            ->push('<html> <body><h1>Chest  pain</h1><p>Call emergency services.</p></body></html>') // same text
            ->push('<html><body><h1>Chest pain</h1><p>Updated advice.</p></body></html>')]);

        $this->actingAsUser($this->makeAdmin());
        $sourceId = $this->postJson('/api/v1/admin/knowledge-sources', ['name' => 'Test publisher', 'license' => 'CC BY 4.0'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/knowledge-sources', ['name' => 'No licence'])->assertUnprocessable()->assertJsonValidationErrors('license');

        $docId = $this->postJson('/api/v1/admin/source-documents', [
            'knowledge_source_id' => $sourceId, 'url' => 'https://guidelines.example/chest', 'title' => 'Chest pain guideline',
        ])->assertCreated()->json('data.id');

        $guideline = MedicalGuideline::first();
        $guideline->update(['source_document_id' => $docId, 'review_status' => 'approved', 'reviewed_by_name' => 'Dr Test']);

        $first = $this->postJson("/api/v1/admin/source-documents/$docId/fetch")->assertOk();
        $this->assertSame('active', $first->json('meta.status'));
        $this->assertFalse($first->json('meta.changed'));
        $hash = SourceDocument::find($docId)->content_hash;
        $this->assertSame(64, strlen($hash));
        $this->assertStringNotContainsString('x()', SourceDocument::find($docId)->raw_content);

        $this->postJson("/api/v1/admin/source-documents/$docId/fetch")->assertJsonPath('meta.changed', false); // whitespace only
        $this->assertSame('approved', $guideline->fresh()->review_status);

        $this->postJson("/api/v1/admin/source-documents/$docId/fetch")
            ->assertJsonPath('meta.changed', true)->assertJsonPath('meta.sent_to_review', 1);
        $this->assertSame('in_review', $guideline->fresh()->review_status);
        $this->assertSame('changed', SourceDocument::find($docId)->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.source_changed']);
    }

    public function test_fetch_refuses_private_and_non_http_addresses(): void
    {
        Http::fake();
        $this->actingAsUser($this->makeAdmin());
        $source = KnowledgeSource::create(['name' => 'S', 'license' => 'x']);

        foreach (['http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data', 'http://10.0.0.5/', 'http://localhost/', 'http://[::1]/'] as $i => $url) {
            $doc = SourceDocument::create(['knowledge_source_id' => $source->id, 'url' => $url, 'title' => "t$i"]);
            $this->postJson("/api/v1/admin/source-documents/{$doc->id}/fetch")->assertUnprocessable()->assertJsonValidationErrors('url');
        }
        Http::assertNothingSent();
    }

    public function test_redirect_to_private_address_is_blocked(): void
    {
        Http::fake(['guidelines.example/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret'])]);
        $this->actingAsUser($this->makeAdmin());
        $source = KnowledgeSource::create(['name' => 'S', 'license' => 'x']);
        $doc = SourceDocument::create(['knowledge_source_id' => $source->id, 'url' => 'https://guidelines.example/a', 'title' => 't']);

        $this->postJson("/api/v1/admin/source-documents/{$doc->id}/fetch")->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_unreachable_document_is_marked_unavailable(): void
    {
        Http::fake(['guidelines.example/*' => Http::response('gone', 404)]);
        $this->actingAsUser($this->makeAdmin());
        $source = KnowledgeSource::create(['name' => 'S', 'license' => 'x']);
        $doc = SourceDocument::create(['knowledge_source_id' => $source->id, 'url' => 'https://guidelines.example/b', 'title' => 't']);

        $this->postJson("/api/v1/admin/source-documents/{$doc->id}/fetch")->assertOk()->assertJsonPath('meta.status', 'unavailable');
        $this->artisan('docthub:sources:check')->assertSuccessful();
    }

    public function test_data_collection_endpoints_are_admin_only(): void
    {
        $this->actingAsUser($this->makePatient());
        $this->getJson('/api/v1/admin/analytics')->assertForbidden();
        $this->get('/api/v1/admin/analytics/export')->assertForbidden();
        $this->getJson('/api/v1/admin/knowledge-sources')->assertForbidden();
    }
}
