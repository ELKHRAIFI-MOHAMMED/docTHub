<?php

namespace Tests\Feature;

use App\Models\BodyPart;
use App\Models\Country;
use App\Models\Disease;
use App\Models\KnowledgeRelease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\SymptomAssessment;
use App\Models\SymptomQuestion;
use App\Services\Knowledge\Release\KnowledgeSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * knowledge:export (versioned snapshots of approved knowledge) and the knowledge version stamped on assessments.
 * Reviewer names, sources and translations below are test fixtures, not real reviews.
 */
class KnowledgeReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedCatalog();
        $this->allowUnreviewedContent(false);
        // Sample catalog made valid for the tests (see KnowledgeValidateTest).
        OrientationRule::where('result_type', 'emergency')->update(['reviewed_by_name' => 'Test reviewer']);
        Disease::where('status', 'published')->whereDoesntHave('symptoms')->update(['status' => 'draft']);
    }

    private function approve(Disease $disease): Disease
    {
        foreach (['fr', 'ar'] as $locale) {
            $disease->syncTranslations([$locale => array_filter(['name' => "Test $locale", 'description' => $disease->description ? "Test $locale" : null])]);
        }
        $disease->update(['review_status' => 'approved', 'source_name' => 'Test source', 'reviewed_by_name' => 'Test reviewer', 'severity' => $disease->severity ?? 'moderate']);

        return $disease;
    }

    private function linkedDisease(int $skip = 0): Disease
    {
        return Disease::whereHas('symptoms')->whereHas('bodyParts')->whereNotNull('specialty_id')->orderBy('code')->skip($skip)->firstOrFail();
    }

    public function test_export_is_refused_while_validation_fails_or_nothing_is_approved(): void
    {
        $this->artisan('knowledge:export')->expectsOutputToContain('Nothing is approved')->assertExitCode(1);

        OrientationRule::where('result_type', 'emergency')->update(['reviewed_by_name' => null]);
        $this->artisan('knowledge:export')->expectsOutputToContain('validation error')->assertExitCode(1);

        $this->assertDatabaseCount('knowledge_releases', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('exports'));
    }

    public function test_first_export_contains_only_approved_items_and_what_they_reference(): void
    {
        $disease = $this->approve($this->linkedDisease());

        $this->artisan('knowledge:export', ['--notes' => 'First release'])->expectsOutputToContain('v1.0')->assertExitCode(0);

        $release = KnowledgeRelease::sole();
        $this->assertSame(['1.0', 1, 0, 'exports/knowledge_v1.0.json'], [$release->version, $release->major, $release->minor, $release->file_path]);
        $json = Storage::disk('local')->get('exports/knowledge_v1.0.json');
        $this->assertSame(hash('sha256', $json), $release->file_sha256);

        $doc = json_decode($json, true);
        $this->assertSame('1.0', $doc['knowledge_version']);
        $this->assertSame($release->content_hash, $doc['content_hash']);
        $this->assertSame([$disease->code], array_column($doc['items']['diseases'], 'code'), 'drafts are never exported');
        $this->assertSame([], $doc['items']['rules'], 'no rule is approved');
        $exported = $doc['items']['diseases'][0];
        $this->assertEquals(['name' => 'Test fr'] + ($disease->description ? ['description' => 'Test fr'] : []), $exported['translations']['fr']);
        $this->assertSame('Test reviewer', $exported['review']['reviewed_by_name']);
        $this->assertNotNull($exported['review']['reviewed_at']);
        $this->assertSame($disease->specialty->code, $exported['specialty']);
        $this->assertEqualsCanonicalizing($disease->symptoms->pluck('code')->all(), array_column($doc['items']['symptoms'], 'code'), 'referenced symptoms only');
        $this->assertCount(1, $doc['items']['specialties']);
        $this->assertSame('First release', $release->notes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.exported', 'auditable_type' => 'knowledge_release', 'auditable_id' => $release->id]);
    }

    public function test_versions_bump_only_when_approved_content_changes_and_files_are_never_overwritten(): void
    {
        $this->approve($this->linkedDisease());
        $this->artisan('knowledge:export')->assertExitCode(0);
        $v1 = Storage::disk('local')->get('exports/knowledge_v1.0.json');

        $this->artisan('knowledge:export')->expectsOutputToContain('unchanged since v1.0')->assertExitCode(0);
        $this->assertDatabaseCount('knowledge_releases', 1);

        // A draft changing does not change the approved knowledge.
        Disease::where('review_status', 'draft')->first()->update(['name' => 'Changed draft']);
        $this->artisan('knowledge:export')->expectsOutputToContain('unchanged')->assertExitCode(0);

        $this->approve($this->linkedDisease(1));
        $this->artisan('knowledge:export')->expectsOutputToContain('v1.1')->assertExitCode(0);
        $guideline = MedicalGuideline::first();
        $guideline->syncTranslations(['fr' => ['title' => 'T', 'content' => 'C'], 'ar' => ['title' => 'T', 'content' => 'C']]);
        $guideline->update(['review_status' => 'approved', 'reviewed_by_name' => 'Test reviewer', 'source_name' => 'Test source']);
        $this->artisan('knowledge:export', ['--major' => true])->expectsOutputToContain('v2.0')->assertExitCode(0);

        $this->assertSame(['1.0', '1.1', '2.0'], KnowledgeRelease::orderBy('id')->pluck('version')->all());
        $this->assertSame($v1, Storage::disk('local')->get('exports/knowledge_v1.0.json'), 'v1.0 untouched');
    }

    public function test_snapshot_is_deterministic(): void
    {
        $disease = $this->approve($this->linkedDisease());
        $first = app(KnowledgeSnapshot::class)->build()['content_hash'];

        // Same content saved again (new updated_at, translations rewritten in another order): same hash.
        $disease->translations()->delete();
        $disease->syncTranslations(['ar' => ['name' => 'Test ar'], 'fr' => ['name' => 'Test fr']]);
        if ($disease->description) {
            $disease->syncTranslations(['ar' => ['description' => 'Test ar'], 'fr' => ['description' => 'Test fr']]);
        }
        $disease->touch();

        $this->assertSame($first, app(KnowledgeSnapshot::class)->build()['content_hash']);
    }

    public function test_assessments_record_the_knowledge_version_in_force(): void
    {
        $start = fn () => $this->postJson('/api/v1/assessments', [
            'body_part_ids' => BodyPart::where('code', 'head')->pluck('id')->all(),
            'country_id' => Country::where('iso2', 'MA')->value('id'),
        ])->assertCreated()->json('data.id');
        $version = fn (string $uuid) => SymptomAssessment::where('uuid', $uuid)->value('knowledge_version');

        $this->assertSame('unreleased', $version($start()), 'no release yet');

        $disease = $this->approve($this->linkedDisease());
        $this->artisan('knowledge:export')->assertExitCode(0);
        $uuid = $start();
        $this->assertSame('1.0', $version($uuid));

        // Approved content edited in the admin after the export: the cache is refreshed, the stamp says so.
        $this->actingAsUser($this->makeAdmin());
        $this->putJson("/api/v1/admin/diseases/{$disease->id}", ['name' => 'Edited after release'])->assertOk();
        $this->assertSame('unreleased', $version($start()));

        // Evaluation refreshes the stamp (results depend on the content at evaluation time).
        $question = SymptomQuestion::where('code', 'head_assoc')->firstOrFail();
        $this->postJson("/api/v1/assessments/$uuid/answers", ['answers' => [['question_id' => $question->id, 'option_codes' => ['speech_difficulty']]]])
            ->assertJsonPath('data.emergency_detected', true);
        $this->postJson("/api/v1/assessments/$uuid/evaluate")->assertOk()->assertJsonPath('data.knowledge_version', 'unreleased');

        $this->allowUnreviewedContent(true);
        $this->assertSame('unreviewed', $version($start()));
    }

    public function test_analytics_group_assessments_by_knowledge_version(): void
    {
        SymptomAssessment::query()->delete();
        $this->actingAsUser($this->makeAdmin());
        $base = ['locale' => 'en', 'body_part_ids' => [1], 'status' => 'completed', 'evaluated_at' => now()];
        SymptomAssessment::create($base + ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'knowledge_version' => '1.0']);
        SymptomAssessment::create($base + ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'knowledge_version' => '1.0']);
        SymptomAssessment::create($base + ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'knowledge_version' => 'unreleased']);

        // Version names contain a dot: compare the whole map, not a JSON path.
        $this->assertEquals(['1.0' => 2, 'unreleased' => 1], $this->getJson('/api/v1/admin/analytics?days=30')->assertOk()->json('data.knowledge_versions'));
    }
}
