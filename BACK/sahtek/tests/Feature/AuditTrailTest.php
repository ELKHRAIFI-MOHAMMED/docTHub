<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Disease;
use App\Models\KnowledgeSource;
use App\Models\Symptom;
use App\Models\SymptomQuestion;
use App\Services\Knowledge\FetchedDocument;
use App\Services\Knowledge\RawDocumentRecorder;
use Database\Seeders\KnowledgeSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Audit trail: who changed what, when, old and new values — columns, translations, links, command line. */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase, WithConsoleEvents; // Laravel only reroutes console events in tests with this trait

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    public function test_admin_changes_record_old_and_new_values_of_columns_translations_and_links(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAsUser($admin);
        $disease = Disease::whereHas('symptoms')->firstOrFail();
        $oldName = $disease->name;
        $oldFr = $disease->translate('name', 'fr');
        $oldSymptoms = $disease->symptoms()->orderBy('code')->pluck('code')->all();
        $newSymptom = Symptom::whereNotIn('code', $oldSymptoms)->firstOrFail();

        $this->putJson("/api/v1/admin/diseases/{$disease->id}", [
            'name' => 'Renamed (test)',
            'translations' => ['fr' => ['name' => 'Renommée (test)']],
            'symptoms' => [['id' => $newSymptom->id, 'weight' => 2]],
        ])->assertOk();

        $log = AuditLog::where('action', 'admin.updated')->where('auditable_type', 'disease')->where('auditable_id', $disease->id)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($oldName, $log->old_values['name']);
        $this->assertSame('Renamed (test)', $log->new_values['name']);
        $this->assertSame($oldFr, $log->old_values['translations']['fr']['name'] ?? null);
        $this->assertSame('Renommée (test)', $log->new_values['translations']['fr']['name']);
        $this->assertSame($oldSymptoms, array_keys($log->old_values['symptoms']));
        $this->assertSame([$newSymptom->code => 2], $log->new_values['symptoms']);
        $this->assertArrayNotHasKey('updated_at', $log->new_values);

        // A save that changes nothing is not journaled.
        $this->putJson("/api/v1/admin/diseases/{$disease->id}", ['name' => 'Renamed (test)'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.updated')->where('auditable_id', $disease->id)->count());
    }

    public function test_rule_version_bump_keeps_the_changes_of_the_first_save(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $rule = \App\Models\OrientationRule::where('result_type', 'specialty')->firstOrFail();

        $this->putJson("/api/v1/admin/rules/{$rule->id}", ['priority' => 42, 'weight' => 3])->assertOk();

        $log = AuditLog::where('action', 'admin.updated')->where('auditable_type', 'rule')->where('auditable_id', $rule->id)->sole();
        $this->assertSame(42, $log->new_values['priority']);
        $this->assertSame(3, $log->new_values['weight']);
    }

    public function test_question_options_are_journaled(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $question = SymptomQuestion::whereHas('options')->firstOrFail();
        $options = $question->options->map(fn ($o) => ['code' => $o->code, 'label' => $o->label])->all();
        $options[0]['label'] = 'Edited label (test)';

        $this->putJson("/api/v1/admin/questions/{$question->id}", ['options' => $options])->assertOk();

        $log = AuditLog::where('action', 'admin.updated')->where('auditable_type', 'question')->sole();
        $this->assertSame('Edited label (test)', collect($log->new_values['options'])->firstWhere('code', $options[0]['code'])['label']);
        $this->assertNotSame('Edited label (test)', collect($log->old_values['options'])->firstWhere('code', $options[0]['code'])['label']);
    }

    public function test_command_line_changes_name_the_command_and_history_shows_them(): void
    {
        Storage::fake('local');
        $this->seed(KnowledgeSourceSeeder::class);
        $source = KnowledgeSource::where('code', 'icd11')->firstOrFail();
        $source->update(['license_status' => 'confirmed']);
        $body = json_encode(['@id' => 'http://id.who.int/icd/release/11/2026-01/mms/1', 'title' => ['@language' => 'en', '@value' => 'Fixture entity'], 'code' => '1A00', 'classKind' => 'category']);
        app(RawDocumentRecorder::class)->record($source, new FetchedDocument('1A00', 'https://id.who.int/icd/release/11/2026-01/mms/1', 'en', 'Fixture', $body, 'json'));

        $this->artisan('knowledge:import', ['source' => 'icd11', '--as' => 'disease'])->assertExitCode(0);

        $log = AuditLog::where('action', 'knowledge.import.created')->sole();
        $this->assertNull($log->user_id);
        $this->assertSame('artisan knowledge:import', $log->user_agent);
        $this->assertNull($log->ip_address);

        $this->artisan('knowledge:history', ['item' => 'disease:icd11_1a00'])
            ->expectsOutputToContain('artisan knowledge:import')->assertExitCode(0);
        $this->artisan('knowledge:history', ['item' => 'disease:nope'])->assertExitCode(1);
        $this->artisan('knowledge:history', ['item' => 'nothing'])->assertExitCode(2);
    }

    public function test_audit_log_can_be_filtered_by_item_and_period(): void
    {
        $this->actingAsUser($this->makeAdmin());
        $disease = Disease::firstOrFail();
        $this->putJson("/api/v1/admin/diseases/{$disease->id}", ['name' => 'Filter test'])->assertOk();
        $this->putJson('/api/v1/admin/symptoms/'.Symptom::firstOrFail()->id, ['name' => 'Other change'])->assertOk();

        $this->getJson("/api/v1/admin/audit-logs?auditable_type=disease&auditable_id={$disease->id}")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.new_values.name', 'Filter test');
        $this->getJson('/api/v1/admin/audit-logs?auditable_type=disease&from='.now()->addDay()->toDateString())->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/audit-logs?from=not-a-date')->assertJsonValidationErrors('from');
    }
}
