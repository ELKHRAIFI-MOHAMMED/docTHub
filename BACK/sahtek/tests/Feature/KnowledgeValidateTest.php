<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\Specialty;
use App\Models\Symptom;
use App\Services\Knowledge\Validation\Issue;
use App\Services\Knowledge\Validation\KnowledgeValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/** knowledge:validate: every check of the quality gate, on the sample catalog and on targeted defects. */
class KnowledgeValidateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    /** @return array<string, string[]> check => items with an ERROR */
    private function errors(): array
    {
        $out = [];
        foreach (app(KnowledgeValidator::class)->run() as $issue) {
            if ($issue->level === 'error') {
                $out[$issue->check][] = $issue->item;
            }
        }

        return $out;
    }

    /** Test fixture: makes the sample catalog pass (reviewer names are test data, not real reviews). */
    private function makeSampleCatalogPass(): void
    {
        OrientationRule::where('result_type', 'emergency')->update(['reviewed_by_name' => 'Test reviewer']);
        Disease::where('status', 'published')->whereDoesntHave('symptoms')->update(['status' => 'draft']);
    }

    public function test_the_sample_catalog_fails_on_unreviewed_emergency_rules_and_unlinked_published_conditions(): void
    {
        $errors = $this->errors();

        $this->assertSame(['disease_incomplete', 'emergency_reviewer'], array_keys($errors), 'no other kind of defect in the sample catalog');
        $this->assertCount(OrientationRule::where('result_type', 'emergency')->count(), $errors['emergency_reviewer']);
        $this->artisan('knowledge:validate')->expectsOutputToContain('NOT valid')->assertExitCode(1);
    }

    public function test_a_clean_base_passes_and_drafts_only_warn(): void
    {
        $this->makeSampleCatalogPass();

        $this->assertSame([], $this->errors());
        $warnings = array_filter(app(KnowledgeValidator::class)->run(), fn (Issue $i) => $i->level === 'warning');
        $this->assertNotEmpty($warnings, 'unlinked drafts are reported as warnings');
        $this->artisan('knowledge:validate')->expectsOutputToContain('Knowledge base valid')->assertExitCode(0);
        $this->artisan('knowledge:validate', ['--strict' => true])->assertExitCode(1);
    }

    public function test_approved_items_need_three_languages_a_source_a_reviewer_and_a_version(): void
    {
        $this->makeSampleCatalogPass();
        $disease = Disease::whereHas('symptoms')->whereHas('bodyParts')->whereNotNull('specialty_id')->firstOrFail();
        $disease->translations()->where(['locale' => 'ar', 'field' => 'name'])->delete();
        $disease->update(['review_status' => 'approved', 'source_name' => null, 'source_document_id' => null, 'reviewed_by_name' => null, 'version' => 0]);

        $messages = collect(app(KnowledgeValidator::class)->run())->where('item', "disease:{$disease->code}")->pluck('message')->all();
        $this->assertContains('approved without ar translation of name', $messages);
        $this->assertContains('approved without a source', $messages);
        $this->assertContains('approved without a reviewer', $messages);
        $this->assertContains('approved without a version', $messages);

        // Completing it clears the errors (French and Arabic text are test fixtures).
        $disease->syncTranslations(['ar' => ['name' => 'اختبار'], 'fr' => ['name' => 'Test FR']]);
        foreach (['fr', 'ar'] as $locale) {
            if ($disease->description) {
                $disease->syncTranslations([$locale => ['description' => "Test $locale"]]);
            }
        }
        $disease->update(['source_name' => 'Test source', 'reviewed_by_name' => 'Test reviewer', 'version' => 1]);
        $this->assertNotNull($disease->fresh()->reviewed_at, 'approval date stamped');
        $this->assertArrayNotHasKey('approved_incomplete', $this->errors());

        $guideline = MedicalGuideline::firstOrFail();
        $guideline->update(['review_status' => 'approved', 'reviewed_by_name' => null]);
        $this->assertContains("guideline:#{$guideline->id}", $this->errors()['approved_incomplete']);
    }

    public function test_links_to_deleted_or_unknown_items_are_errors(): void
    {
        $this->makeSampleCatalogPass();
        $rule = OrientationRule::where('result_type', 'disease')->whereNotNull('disease_id')->firstOrFail();
        $rule->disease->delete(); // soft delete: the rule now points to nothing usable

        $other = OrientationRule::where('result_type', 'specialty')->firstOrFail();
        $other->update(['conditions' => [['question' => 'no_such_question', 'operator' => 'answered', 'value' => null]]]);

        $symptom = Symptom::whereHas('diseases', fn ($q) => $q->where('diseases.id', '!=', $rule->disease_id))->firstOrFail();
        $symptom->delete();

        $broken = $this->errors()['broken_link'] ?? [];
        $this->assertContains("rule:{$rule->code}", $broken);
        $this->assertContains("rule:{$other->code}", $broken);
        $this->assertNotEmpty(array_filter($broken, fn ($item) => str_starts_with($item, 'disease:') || str_starts_with($item, 'question_option:')));
    }

    public function test_invalid_priority_severity_and_duplicate_external_identity(): void
    {
        $this->makeSampleCatalogPass();
        $rule = OrientationRule::where('result_type', 'advice')->firstOrFail();
        $rule->update(['priority' => 150]);
        $approved = Disease::firstOrFail();
        $approved->update(['severity' => null, 'review_status' => 'approved']);
        Symptom::create(['code' => 'icd11_test', 'name' => 'Fixture', 'external_system' => 'icd11', 'external_code' => 'TEST']);
        Disease::create(['code' => 'icd11_test', 'name' => 'Fixture', 'external_system' => 'icd11', 'external_code' => 'TEST']);

        $errors = $this->errors();
        $this->assertContains("rule:{$rule->code}", $errors['invalid_value']);
        $this->assertContains("disease:{$approved->code}", $errors['invalid_value']);
        $this->assertContains('icd11:TEST', $errors['duplicate_code']);
    }

    public function test_an_approved_or_published_condition_must_be_linked(): void
    {
        $this->makeSampleCatalogPass();
        $disease = Disease::create(['code' => 'fixture_unlinked', 'name' => 'Fixture', 'status' => 'draft']);
        $this->assertArrayNotHasKey('disease_incomplete', $this->errors(), 'draft: warning only');

        $disease->update(['status' => 'published']);
        $this->assertSame(['disease:fixture_unlinked'], $this->errors()['disease_incomplete']);

        $specialty = Specialty::firstOrFail();
        $disease->update(['specialty_id' => $specialty->id]);
        $disease->symptoms()->attach(Symptom::firstOrFail()->id, ['weight' => 1]);
        $this->assertSame(['disease:fixture_unlinked'], $this->errors()['disease_incomplete'], 'still no body part');
    }

    public function test_rejected_and_retired_emergency_rules_need_no_reviewer(): void
    {
        $this->makeSampleCatalogPass();
        $rule = OrientationRule::where('result_type', 'emergency')->firstOrFail();
        $rule->update(['reviewed_by_name' => null]);
        $this->assertSame(["rule:{$rule->code}"], $this->errors()['emergency_reviewer']);

        $rule->update(['validation_status' => 'retired']);
        $this->assertArrayNotHasKey('emergency_reviewer', $this->errors());
    }

    public function test_json_and_markdown_reports(): void
    {
        $issues = app(KnowledgeValidator::class)->run();
        $this->assertSame(['level', 'check', 'item', 'message'], array_keys($issues[0]->toArray()));
        $this->artisan('knowledge:validate', ['--format' => 'json'])
            ->expectsOutputToContain('"check": "emergency_reviewer"')->assertExitCode(1); // one JSON write: one expectation

        $this->artisan('knowledge:validate', ['--format' => 'markdown'])
            ->expectsOutputToContain('| FAIL | emergency_reviewer |')->assertExitCode(1);
    }

    public function test_admin_records_the_reviewer_and_limits_priority(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $rule = OrientationRule::where('result_type', 'emergency')->firstOrFail();

        $this->putJson("/api/v1/admin/rules/{$rule->id}", ['priority' => 101])->assertJsonValidationErrors('priority');
        $this->putJson("/api/v1/admin/rules/{$rule->id}", ['reviewed_by_name' => 'Test reviewer'])->assertOk();
        $this->assertSame('Test reviewer', $rule->fresh()->reviewed_by_name);
        $this->assertNull($rule->fresh()->reviewed_at, 'not approved: no approval date');
    }
}
