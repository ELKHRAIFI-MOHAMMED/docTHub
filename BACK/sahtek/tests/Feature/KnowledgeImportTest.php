<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\KnowledgeAlias;
use App\Models\KnowledgeSource;
use App\Models\OrientationRule;
use App\Models\Symptom;
use App\Services\Knowledge\FetchedDocument;
use App\Services\Knowledge\Import\Normalizer;
use App\Services\Knowledge\RawDocumentRecorder;
use Database\Seeders\KnowledgeSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * knowledge:import, the structuring layer. Raw documents are written with the real recorder from fixtures shaped
 * like the official responses (not medical content). No network.
 */
class KnowledgeImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->seed(KnowledgeSourceSeeder::class);
        KnowledgeSource::whereIn('code', ['icd11', 'medlineplus'])->update(['license_status' => 'confirmed']);
    }

    private function source(string $code): KnowledgeSource
    {
        return KnowledgeSource::where('code', $code)->firstOrFail();
    }

    private function icd(string $lang, string $title, string $code = '1A00', string $classKind = 'category', string $id = '123456'): void
    {
        $uri = "http://id.who.int/icd/release/11/2026-01/mms/$id";
        $body = json_encode(['@id' => $uri, 'title' => ['@language' => $lang, '@value' => $title], 'code' => $code, 'classKind' => $classKind], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        app(RawDocumentRecorder::class)->record($this->source('icd11'), new FetchedDocument(
            $code, "https://id.who.int/icd/release/11/2026-01/mms/$id", $lang, "$code $title", $body, 'json', 200, ['code' => $code]));
    }

    private function medlinePlus(string $term, string $topics): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><nlmSearchResult><term>'.$term.'</term><count>1</count><list num="1" start="0" per="10">'.$topics.'</list></nlmSearchResult>';
        app(RawDocumentRecorder::class)->record($this->source('medlineplus'), new FetchedDocument(
            $term, 'https://wsearch.nlm.nih.gov/ws/query?term='.rawurlencode($term), 'en', "MedlinePlus: $term", $xml, 'xml'));
    }

    private function topic(string $id, string $title, array $alsoCalled = [], string $summary = '&lt;p&gt;Fixture summary.&lt;/p&gt;', string $language = 'English'): string
    {
        $also = implode('', array_map(fn ($a) => "<also-called>$a</also-called>", $alsoCalled));

        return '<document rank="0" url="https://medlineplus.gov/t'.$id.'.html"><content name="healthTopic"><health-topic meta-desc="x" title="'.$title.'" url="https://medlineplus.gov/t'.$id.'.html" id="'.$id.'" language="'.$language.'" date-created="01/01/2000">'
            .$also.'<full-summary>'.$summary.'</full-summary><group url="https://medlineplus.gov/g.html" id="1">Group</group><site title="Linked page" url="https://medlineplus.gov/ency/article/000001.htm"><organization>Other</organization></site></health-topic></content></document>';
    }

    public function test_icd11_import_keeps_only_code_title_uri_as_a_draft_without_any_link(): void
    {
        $this->icd('en', 'Fixture entity');
        $this->icd('fr', 'Entité de test');
        $this->icd('ar', 'كيان اختبار');
        $rules = OrientationRule::count();

        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->assertExitCode(0);

        $disease = Disease::where('code', 'icd11_1a00')->firstOrFail();
        $this->assertSame('Fixture entity', $disease->name);
        $this->assertSame(['icd11', '1A00', 'http://id.who.int/icd/release/11/2026-01/mms/123456'], [$disease->external_system, $disease->external_code, $disease->external_uri]);
        $this->assertSame(['draft', 'draft', 1, false], [$disease->status, $disease->review_status, $disease->version, $disease->is_sample]);
        $this->assertNull($disease->severity, 'no invented severity');
        $this->assertNull($disease->description, 'code, title and URI only');
        $this->assertNull($disease->specialty_id);
        $this->assertSame($this->source('icd11')->name, $disease->source_name);
        $this->assertSame($this->source('icd11')->documents()->where('language', 'en')->value('id'), $disease->source_document_id);
        $this->assertSame('Entité de test', $disease->translate('name', 'fr'));
        $this->assertSame('كيان اختبار', $disease->translate('name', 'ar'));
        $this->assertNull($disease->needs_translation);
        $this->assertSame(0, $disease->symptoms()->count() + $disease->bodyParts()->count());
        $this->assertSame($rules, OrientationRule::count(), 'no rule is ever written');
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.import.created', 'auditable_type' => 'disease', 'auditable_id' => $disease->id]);
    }

    public function test_missing_languages_are_left_empty_and_marked(): void
    {
        $this->icd('en', 'Fixture entity');
        $this->icd('fr', 'Entité de test');

        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'symptom'])->expectsOutputToContain('ar')->assertExitCode(0);

        $symptom = Symptom::where('code', 'icd11_1a00')->firstOrFail();
        $this->assertSame(['ar'], $symptom->needs_translation);
        $this->assertSame(0, $symptom->translations()->where('locale', 'ar')->count());
        $this->assertSame('Fixture entity', $symptom->translate('name', 'ar'), 'falls back to English, nothing invented');
        $this->assertSame(['draft', 1], [$symptom->status, $symptom->version]);
    }

    public function test_import_is_idempotent(): void
    {
        $this->icd('en', 'Fixture entity');
        $this->icd('fr', 'Entité de test');
        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->assertExitCode(0);
        $counts = [Disease::count(), \App\Models\Translation::count(), \App\Models\AuditLog::where('action', 'like', 'knowledge.import%')->count()];

        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->expectsOutputToContain('unchanged')->assertExitCode(0);

        $this->assertSame($counts, [Disease::count(), \App\Models\Translation::count(), \App\Models\AuditLog::where('action', 'like', 'knowledge.import%')->count()]);
    }

    public function test_a_new_source_version_updates_a_draft_but_never_a_reviewed_item(): void
    {
        $this->icd('en', 'Fixture entity');
        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->assertExitCode(0);

        $this->icd('en', 'Fixture entity, revised');
        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->expectsOutputToContain('updated')->assertExitCode(0);
        $disease = Disease::where('code', 'icd11_1a00')->firstOrFail();
        $this->assertSame('Fixture entity, revised', $disease->name);
        $this->assertSame(1, $disease->version);
        $this->assertSame(1, Disease::where('external_code', '1A00')->count());

        $disease->update(['review_status' => 'in_review']);
        $this->icd('en', 'Fixture entity, revised twice');
        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->expectsOutputToContain('conflict')->assertExitCode(0);
        $this->assertSame('Fixture entity, revised', $disease->fresh()->name);
        $this->assertSame('in_review', $disease->fresh()->review_status);
    }

    public function test_chapters_blocks_and_entities_without_english_are_not_imported(): void
    {
        $this->icd('en', 'Fixture block', '', 'block', '111');
        $this->icd('fr', 'Seulement en français', '1B00', 'category', '222');

        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])
            ->expectsOutputToContain('chapters and blocks are not imported')
            ->expectsOutputToContain('no English version')
            ->assertExitCode(1);
        $this->assertSame(0, Disease::whereNotNull('external_system')->count());
    }

    public function test_the_type_is_never_changed_by_a_later_import(): void
    {
        $this->icd('en', 'Fixture entity');
        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'symptom'])->assertExitCode(0);
        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->expectsOutputToContain('already imported as symptom')->assertExitCode(0);
        $this->assertSame(0, Disease::where('code', 'icd11_1a00')->count());
    }

    public function test_medlineplus_topics_are_chosen_explicitly_and_synonyms_are_deduplicated(): void
    {
        $this->medlinePlus('fixture one', $this->topic('101', 'Fixture topic', ['Fixture synonym', 'fixture  SYNONYM', 'Fixture topic', 'Shared term'],
            '&lt;h3&gt;What is it?&lt;/h3&gt;&lt;p&gt;First &amp;amp; second&lt;/p&gt;&lt;ul&gt;&lt;li&gt;item one&lt;/li&gt;&lt;li&gt;item two&lt;/li&gt;&lt;/ul&gt;')
            .$this->topic('102', 'Other topic', ['shared term']));

        // Without --only: the topics are listed, nothing is imported.
        $this->artisan('knowledge:import', ['source' => 'medlineplus'])->expectsOutputToContain('--only')->assertExitCode(2);
        $this->assertSame(0, Disease::whereNotNull('external_system')->count());
        $this->artisan('knowledge:import', ['source' => 'medlineplus', '--only' => ['101']])->expectsOutputToContain('--as is required')->assertExitCode(2);

        $this->artisan('knowledge:import', ['source' => 'medlineplus', '--only' => ['101', '102'], '--as' => 'disease'])
            ->expectsOutputToContain('already belongs to mplus_101')->assertExitCode(0);

        $topic = Disease::where('code', 'mplus_101')->firstOrFail();
        $this->assertSame("What is it?\n\nFirst & second\n\n- item one\n- item two", $topic->description);
        $this->assertSame(['medlineplus', '101', 'https://medlineplus.gov/t101.html'], [$topic->external_system, $topic->external_code, $topic->external_uri]);
        $this->assertSame(['fr', 'ar'], $topic->needs_translation);
        $this->assertSame(['Fixture synonym', 'Shared term'], $topic->aliases()->orderBy('id')->pluck('alias')->all());
        $this->assertSame(0, Disease::where('code', 'mplus_102')->firstOrFail()->aliases()->count());
        $this->assertSame(2, KnowledgeAlias::count());
        $this->assertStringNotContainsString('Linked page', (string) $topic->description, 'linked pages are never imported');
    }

    public function test_dry_run_writes_nothing_and_unconfirmed_licences_block_the_import(): void
    {
        $this->icd('en', 'Fixture entity');
        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease', '--dry-run' => true])
            ->expectsOutputToContain('created')->expectsOutputToContain('Dry run')->assertExitCode(0);
        $this->assertSame(0, Disease::where('code', 'icd11_1a00')->count());

        KnowledgeSource::where('code', 'icd11')->update(['license_status' => 'unclear']);
        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->expectsOutputToContain('not confirmed')->assertExitCode(1);
        $this->assertSame(0, Disease::where('code', 'icd11_1a00')->count());
    }

    public function test_normalizer(): void
    {
        $this->assertSame('a & b c', Normalizer::text("  <b>a</b> &amp;\n b&nbsp;c  "));
        $this->assertNull(Normalizer::text('<p> </p>'));
        $this->assertSame('icd11_ba40_z', Normalizer::code('icd11', 'BA40.Z'));
        $this->assertSame('icd11_ba40_z_xk8g', Normalizer::code('icd11', 'BA40.Z&XK8G'));
        $this->assertSame('mplus_101', Normalizer::code('mplus', '101'));
        $this->assertSame(Normalizer::key('Heart-Attack'), Normalizer::key('  heart   attack '));
        $this->expectException(\InvalidArgumentException::class);
        Normalizer::text("\xC3\x28");
    }
}
