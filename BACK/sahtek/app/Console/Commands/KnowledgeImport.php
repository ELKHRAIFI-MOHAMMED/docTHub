<?php

namespace App\Console\Commands;

use App\Models\KnowledgeSource;
use App\Services\Knowledge\Import\IcdReader;
use App\Services\Knowledge\Import\KnowledgeImporter;
use App\Services\Knowledge\Import\MedlinePlusReader;
use App\Services\Knowledge\Release\KnowledgeSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Structuring layer: reads the current raw documents of a source (knowledge:fetch) and fills the existing catalog
 * tables. Everything is created as draft; nothing is linked, approved or published.
 *
 *   php artisan knowledge:import icd11 --as=disease [--only=1A00] [--dry-run]
 *   php artisan knowledge:import medlineplus                       (lists the topics found)
 *   php artisan knowledge:import medlineplus --as=symptom --only=<topic id>
 */
class KnowledgeImport extends Command
{
    protected $signature = 'knowledge:import
        {source : Source code (icd11, medlineplus)}
        {--as= : Target type chosen by you: disease or symptom}
        {--only=* : Limit to ICD-11 codes / MedlinePlus topic ids (required for MedlinePlus)}
        {--dry-run : Show what would change, write nothing}';

    protected $description = 'Import fetched raw documents into the catalog as draft items (no links, never approved)';

    private const READERS = ['icd11' => IcdReader::class, 'medlineplus' => MedlinePlusReader::class];

    public function handle(KnowledgeImporter $importer): int
    {
        $key = (string) $this->argument('source');
        if (! isset(self::READERS[$key])) {
            $this->error("Unknown source \"$key\". Available: ".implode(', ', array_keys(self::READERS)));

            return self::INVALID;
        }

        $source = KnowledgeSource::where('code', $key)->first();
        if (! $source || ! $source->isLicenseConfirmed()) {
            $this->error("Licence of \"$key\" is not confirmed: nothing is imported (see docs/sources.md).");

            return self::FAILURE;
        }

        $only = array_values(array_filter(array_map('trim', $this->option('only'))));
        $read = app(self::READERS[$key])->read($source, $only);

        // MedlinePlus searches mix topics of different kinds: you pick the topics and their type explicitly.
        if ($key === 'medlineplus' && ! $only) {
            $this->table(['Topic id', 'Title', 'Synonyms'], array_map(fn ($r) => [$r->externalCode, $r->names['en'], count($r->aliases['en'] ?? [])], $read['records']));
            $this->error('Choose the topics to import with --only=<topic id> and their type with --as=disease|symptom.');

            return self::INVALID;
        }

        $type = (string) $this->option('as');
        if (! isset(KnowledgeImporter::TYPES[$type])) {
            $this->error('--as is required: disease or symptom (the type is your decision, it is not inferred).');

            return self::INVALID;
        }

        $rows = [];
        $dryRun = (bool) $this->option('dry-run');
        DB::beginTransaction();
        try {
            foreach ($read['records'] as $record) {
                $result = $importer->import($source, $record, $type);
                $missing = $result['model']?->needs_translation ?? [];
                $rows[] = [$record->code, "{$record->system}:{$record->externalCode}", $result['action'], $missing ? implode(', ', $missing) : '-', implode('; ', $result['notes'])];
            }
            $dryRun ? DB::rollBack() : DB::commit();
            KnowledgeSnapshot::forgetHash();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        foreach ($read['skipped'] as $message) {
            $this->line("skipped: $message");
        }
        $this->table(['Code', 'Source id', 'Result', 'Needs translation', 'Notes'], $rows);
        if ($dryRun) {
            $this->warn('Dry run: nothing was written.');
        }
        foreach ($read['errors'] as $message) {
            $this->error($message);
        }
        if ($only && ! $read['records'] && ! $read['errors']) {
            $this->error('None of the requested items was found in the fetched documents.');

            return self::FAILURE;
        }

        return $read['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
