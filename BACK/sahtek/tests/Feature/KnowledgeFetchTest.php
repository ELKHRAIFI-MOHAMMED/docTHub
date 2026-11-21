<?php

namespace Tests\Feature;

use App\Models\Disease;
use App\Models\KnowledgeSource;
use App\Models\MedicalGuideline;
use App\Models\SourceDocument;
use Carbon\CarbonInterval;
use Database\Seeders\KnowledgeSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Mockery;
use Tests\TestCase;

/**
 * knowledge:fetch, the raw layer of the knowledge pipeline. Every HTTP exchange is simulated (Http::fake +
 * preventStrayRequests): these tests never reach the network. Response bodies are minimal fixtures shaped like the
 * official APIs, not medical content.
 */
class KnowledgeFetchTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://icdaccessmanagement.who.int/connect/token';

    private const API = 'https://id.who.int/icd/release/11';

    /** @var array<string, string> entity body per language, changed by tests */
    private array $entityBodies = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        Sleep::fake();
        $this->seed(KnowledgeSourceSeeder::class);
        config([
            'docthub.knowledge.sources.icd11.client_id' => 'test-client',
            'docthub.knowledge.sources.icd11.client_secret' => 'test-secret',
            'docthub.knowledge.sources.icd11.release' => null,
        ]);
        $this->entityBodies = ['en' => $this->entity('en', 'Fixture entity'), 'fr' => $this->entity('fr', 'Entité de test')];
    }

    private function confirm(string $code): void
    {
        KnowledgeSource::where('code', $code)->update(['license_status' => 'confirmed']);
    }

    private function entity(string $lang, string $title): string
    {
        return json_encode([
            '@context' => 'http://id.who.int/icd/contexts/contextForLinearizationEntity.json',
            '@id' => 'http://id.who.int/icd/release/11/2026-01/mms/123456',
            'title' => ['@language' => $lang, '@value' => $title],
            'code' => '1A00',
            'classKind' => 'category',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Simulated WHO token endpoint + ICD API. */
    private function fakeIcd(?callable $entity = null): void
    {
        Http::fake(function (Request $request) use ($entity) {
            $url = $request->url();
            if ($url === self::TOKEN_URL) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 3600, 'token_type' => 'Bearer']);
            }
            if ($url === self::API.'/mms') {
                return Http::response(['title' => 'ICD-11 MMS', 'latestRelease' => 'http://id.who.int/icd/release/11/2026-01/mms', 'release' => []]);
            }
            if ($url === self::API.'/2026-01/mms/codeinfo/1A00') {
                return Http::response(['code' => '1A00', 'stemId' => 'http://id.who.int/icd/release/11/2026-01/mms/123456']);
            }
            if ($url === self::API.'/2026-01/mms/codeinfo/ZZ99') {
                return Http::response(['title' => 'Not found'], 404);
            }
            if ($url === self::API.'/2026-01/mms/123456') {
                return $entity ? $entity($request) : Http::response($this->entityBodies[$request->header('Accept-Language')[0]], 200, ['Content-Type' => 'application/json']);
            }

            return Http::response('unexpected '.$url, 500);
        });
    }

    public function test_nothing_is_downloaded_while_the_licence_is_not_confirmed(): void
    {
        Http::fake();

        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00']])
            ->expectsOutputToContain('is not confirmed')->assertExitCode(1);

        KnowledgeSource::where('code', 'icd11')->update(['license_status' => 'unclear']);
        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00']])->assertExitCode(1);

        Http::assertNothingSent();
        $this->assertDatabaseCount('source_documents', 0);
        $this->assertSame('unverified', KnowledgeSource::where('code', 'medlineplus')->value('license_status'));
    }

    public function test_icd11_entities_are_stored_raw_with_oauth_and_the_documented_headers(): void
    {
        $this->confirm('icd11');
        $this->fakeIcd();

        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00'], '--lang' => ['en', 'fr']])->assertExitCode(0);

        // OAuth2 client credentials, HTTP Basic, scope icdapi_access; the token is requested once and reused.
        Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN_URL && $r->method() === 'POST'
            && $r->header('Authorization')[0] === 'Basic '.base64_encode('test-client:test-secret')
            && $r['grant_type'] === 'client_credentials' && $r['scope'] === 'icdapi_access');
        $this->assertCount(1, Http::recorded(fn (Request $r) => $r->url() === self::TOKEN_URL));

        // Latest release discovered, entity fetched per language with the required headers.
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/2026-01/mms/123456' && $r->hasHeader('Accept-Language', 'fr')
            && $r->hasHeader('API-Version', 'v2') && $r->hasHeader('Accept', 'application/json') && $r->hasHeader('Authorization', 'Bearer test-token'));

        $this->assertDatabaseCount('source_documents', 2);
        foreach (['en', 'fr'] as $lang) {
            $doc = SourceDocument::where('language', $lang)->firstOrFail();
            $sha = hash('sha256', $this->entityBodies[$lang]);
            $this->assertSame($sha, $doc->content_hash);
            $this->assertSame("raw/icd11/$sha.json", $doc->raw_path);
            $this->assertSame($this->entityBodies[$lang], Storage::disk('local')->get($doc->raw_path), 'raw body must be stored untouched');
            $this->assertSame('active', $doc->status);
            $this->assertSame(self::API.'/2026-01/mms/123456', $doc->url);
            $this->assertSame(['release' => '2026-01', 'linearization' => 'mms', 'entity' => '123456', 'code' => '1A00', 'uri' => 'http://id.who.int/icd/release/11/2026-01/mms/123456'], $doc->metadata);
            $this->assertNull($doc->raw_content);
        }
        $this->assertSame('1A00 Entité de test', SourceDocument::where('language', 'fr')->value('title'));
    }

    public function test_changed_content_creates_a_new_version_and_sends_approved_items_back_to_review(): void
    {
        $this->seedCatalog();
        $this->confirm('icd11');
        $this->fakeIcd();
        $run = fn () => $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00'], '--lang' => ['en']])->assertExitCode(0);

        $run();
        $first = SourceDocument::firstOrFail();
        $guideline = MedicalGuideline::first();
        $guideline->update(['source_document_id' => $first->id, 'review_status' => 'approved', 'reviewed_by_name' => 'Dr Test']);
        $disease = Disease::first();
        $disease->update(['source_document_id' => $first->id, 'review_status' => 'draft']);

        // Same content: nothing new, nothing sent to review.
        $run();
        $this->assertDatabaseCount('source_documents', 1);
        $this->assertSame('approved', $guideline->fresh()->review_status);

        // New content: new row, old one superseded (kept), approved item back to review, draft untouched.
        Log::spy();
        $this->entityBodies['en'] = $this->entity('en', 'Fixture entity, revised');
        $run();
        $this->assertDatabaseCount('source_documents', 2);
        $second = SourceDocument::latest('id')->first();
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('active', $second->status);
        $this->assertSame($first->id, $second->previous_document_id);
        $this->assertTrue(Storage::disk('local')->exists($first->raw_path), 'old raw file kept');
        $this->assertSame('in_review', $guideline->fresh()->review_status);
        $this->assertSame('draft', $disease->fresh()->review_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.source_changed', 'auditable_type' => 'source_document', 'auditable_id' => $second->id]);
        Log::shouldHaveReceived('warning')->with('Source modifiée', Mockery::on(fn ($ctx) => $ctx['old_hash'] === $first->content_hash && $ctx['sent_to_review'] === 1));

        // Reverted content: the original version is reactivated, no duplicate row (unique url + language + sha).
        $this->entityBodies['en'] = $this->entity('en', 'Fixture entity');
        $run();
        $this->assertDatabaseCount('source_documents', 2);
        $this->assertSame('active', $first->fresh()->status);
        $this->assertSame('superseded', $second->fresh()->status);
    }

    public function test_server_errors_are_retried_with_exponential_backoff(): void
    {
        config(['docthub.knowledge.sources.icd11.min_interval_seconds' => 0]);
        $this->confirm('icd11');
        $responses = [Http::response('busy', 503), Http::response('busy', 502)];
        $this->fakeIcd(function () use (&$responses) {
            return array_shift($responses) ?? Http::response($this->entityBodies['en']);
        });

        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00'], '--lang' => ['en']])->assertExitCode(0);

        Sleep::assertSequence([Sleep::for(1000)->milliseconds(), Sleep::for(2000)->milliseconds()]);
        $this->assertDatabaseCount('source_documents', 1);
    }

    public function test_rate_limited_requests_honour_retry_after_then_give_up(): void
    {
        config(['docthub.knowledge.sources.icd11.min_interval_seconds' => 0]);
        $this->confirm('icd11');
        $this->fakeIcd(fn () => Http::response('slow down', 429, ['Retry-After' => '5']));

        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00'], '--lang' => ['en']])
            ->expectsOutputToContain('could not be fetched')->assertExitCode(1);

        // 1 attempt + 3 retries, each retry waiting the 5 s asked by the server.
        $this->assertCount(4, Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/mms/123456')));
        Sleep::assertSlept(fn (CarbonInterval $d) => (int) $d->totalMilliseconds === 5000, 3);
        $this->assertDatabaseCount('source_documents', 0);
    }

    public function test_timeouts_are_retried_and_reported(): void
    {
        config(['docthub.knowledge.sources.icd11.min_interval_seconds' => 0]);
        $this->confirm('icd11');
        $this->fakeIcd(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00'], '--lang' => ['en']])->assertExitCode(1);

        Sleep::assertSequence([Sleep::for(1000)->milliseconds(), Sleep::for(2000)->milliseconds(), Sleep::for(4000)->milliseconds()]);
        $this->assertDatabaseCount('source_documents', 0);
    }

    public function test_an_expired_token_is_renewed_once(): void
    {
        $this->confirm('icd11');
        $responses = [Http::response('expired', 401)];
        $this->fakeIcd(function () use (&$responses) {
            return array_shift($responses) ?? Http::response($this->entityBodies['en']);
        });

        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00'], '--lang' => ['en']])->assertExitCode(0);

        $this->assertCount(2, Http::recorded(fn (Request $r) => $r->url() === self::TOKEN_URL));
        $this->assertDatabaseCount('source_documents', 1);
    }

    public function test_unknown_code_fails_without_stopping_the_other_targets(): void
    {
        $this->confirm('icd11');
        $this->fakeIcd();

        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['ZZ99', '1A00', 'not a code'], '--lang' => ['en']])
            ->expectsOutputToContain('code not found')->assertExitCode(1);

        $this->assertDatabaseCount('source_documents', 1);
    }

    public function test_icd11_requires_credentials_and_reports_refused_authentication(): void
    {
        $this->confirm('icd11');
        config(['docthub.knowledge.sources.icd11.client_secret' => null]);
        Http::fake();
        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00']])
            ->expectsOutputToContain('ICD_API_CLIENT_SECRET')->assertExitCode(1);
        Http::assertNothingSent();

        config(['docthub.knowledge.sources.icd11.client_secret' => 'wrong']);
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_client'], 400)]);
        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00']])
            ->expectsOutputToContain('authentication refused')->assertExitCode(1);
        $this->assertCount(1, Http::recorded()); // stopped at the token, no API call
    }

    public function test_medlineplus_search_is_stored_raw_throttled_and_unsupported_languages_are_skipped(): void
    {
        $this->confirm('medlineplus');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<nlmSearchResult><term>fixture term</term><file>viv_test</file><server>test</server><count>0</count><retstart>0</retstart><retmax>10</retmax><list num="0" start="0" per="10"/></nlmSearchResult>';
        Http::fake(['wsearch.nlm.nih.gov/*' => Http::response($xml, 200, ['Content-Type' => 'text/xml'])]);

        $this->artisan('knowledge:fetch', ['source' => 'medlineplus', '--target' => ['chest pain', 'headache'], '--lang' => ['en', 'fr']])
            ->expectsOutputToContain('English and Spanish only')->assertExitCode(0);

        Http::assertSent(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return str_starts_with($r->url(), 'https://wsearch.nlm.nih.gov/ws/query?') && $q['db'] === 'healthTopics'
                && $q['term'] === 'chest pain' && $q['rettype'] === 'topic' && $q['tool'] === 'docthub' && ! isset($q['email']);
        });
        $this->assertCount(2, Http::recorded()); // no request at all for French

        // Same XML for both terms: one raw file (content-addressed), two documents (different URLs).
        $sha = hash('sha256', $xml);
        $this->assertSame($xml, Storage::disk('local')->get("raw/medlineplus/$sha.xml"));
        $this->assertDatabaseCount('source_documents', 2);
        $this->assertSame(['db' => 'healthTopics', 'term' => 'headache', 'count' => 0], SourceDocument::latest('id')->first()->metadata);

        // 80 requests per minute → at least 750 ms between two calls.
        Sleep::assertSlept(fn (CarbonInterval $d) => $d->totalMilliseconds > 700 && $d->totalMilliseconds <= 750, 1);
    }

    public function test_medlineplus_non_xml_response_is_a_failure(): void
    {
        $this->confirm('medlineplus');
        Http::fake(['wsearch.nlm.nih.gov/*' => Http::response('<html>maintenance</html>', 200)]);

        $this->artisan('knowledge:fetch', ['source' => 'medlineplus', '--target' => ['chest pain']])
            ->expectsOutputToContain('unexpected response')->assertExitCode(1);
        $this->assertDatabaseCount('source_documents', 0);
    }

    public function test_invalid_arguments_are_rejected(): void
    {
        Http::fake();
        $this->confirm('icd11');
        $this->artisan('knowledge:fetch', ['source' => 'wikipedia'])->assertExitCode(2);
        $this->artisan('knowledge:fetch', ['source' => 'icd11'])->expectsOutputToContain('Nothing to fetch')->assertExitCode(2);
        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00'], '--lang' => ['de']])->assertExitCode(2);
        Http::assertNothingSent();
    }

    public function test_pipeline_documents_are_not_refetched_by_the_page_monitor(): void
    {
        $this->confirm('icd11');
        $this->fakeIcd();
        $this->artisan('knowledge:fetch', ['source' => 'icd11', '--target' => ['1A00'], '--lang' => ['en']])->assertExitCode(0);
        $doc = SourceDocument::firstOrFail();
        $requests = count(Http::recorded());

        $this->actingAsUser($this->makeAdmin());
        $this->postJson("/api/v1/admin/source-documents/{$doc->id}/fetch")->assertStatus(409);
        $this->artisan('docthub:sources:check')->assertExitCode(0);

        $this->assertCount($requests, Http::recorded());
        $this->assertSame(hash('sha256', $this->entityBodies['en']), $doc->fresh()->content_hash);
    }

    public function test_admin_confirms_a_licence_and_the_date_is_recorded(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $id = KnowledgeSource::where('code', 'medlineplus')->value('id');

        $this->putJson("/api/v1/admin/knowledge-sources/$id", ['license_status' => 'maybe'])->assertJsonValidationErrors('license_status');
        $this->putJson("/api/v1/admin/knowledge-sources/$id", ['license_status' => 'confirmed'])->assertOk();

        $source = KnowledgeSource::find($id);
        $this->assertTrue($source->isLicenseConfirmed());
        $this->assertSame(now()->toDateString(), $source->license_checked_at->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.updated', 'auditable_type' => 'knowledge_source', 'auditable_id' => $id]);

        // Re-seeding never resets a confirmation.
        $this->seed(KnowledgeSourceSeeder::class);
        $this->assertTrue($source->fresh()->isLicenseConfirmed());
    }
}
