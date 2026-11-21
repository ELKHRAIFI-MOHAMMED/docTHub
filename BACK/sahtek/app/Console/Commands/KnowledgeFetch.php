<?php

namespace App\Console\Commands;

use App\Models\KnowledgeSource;
use App\Services\Knowledge\Contracts\SourceFetcher;
use App\Services\Knowledge\FetchedDocument;
use App\Services\Knowledge\FetchException;
use App\Services\Knowledge\FetchFailure;
use App\Services\Knowledge\RawDocumentRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Raw layer of the knowledge pipeline: downloads documents from an external source and stores them untouched
 * (storage/app/raw/{source}/{sha256}.{ext}) with a row in source_documents. Never creates medical content.
 *
 *   php artisan knowledge:fetch icd11 --target=1A00 --target=entity:257068234 --lang=en --lang=fr
 *   php artisan knowledge:fetch medlineplus --target="chest pain"
 */
class KnowledgeFetch extends Command
{
    protected $signature = 'knowledge:fetch
        {source : Source code (icd11, medlineplus)}
        {--target=* : What to fetch (ICD-11 code or entity:<id>; MedlinePlus search term). Default: config targets}
        {--lang=* : Languages (en, fr, ar). Default: languages offered by the source}';

    protected $description = 'Download raw documents from a knowledge source, fingerprint them and detect changes';

    public function handle(RawDocumentRecorder $recorder): int
    {
        $key = (string) $this->argument('source');
        $fetchers = config('docthub.knowledge.fetchers');
        if (! isset($fetchers[$key])) {
            $this->error("Unknown source \"$key\". Available: ".implode(', ', array_keys($fetchers)));

            return self::INVALID;
        }

        $source = KnowledgeSource::where('code', $key)->first();
        if (! $source) {
            $this->error("Knowledge source \"$key\" is not registered. Run: php artisan db:seed --class=KnowledgeSourceSeeder");

            return self::FAILURE;
        }
        if (! $source->is_active) {
            $this->error("Knowledge source \"$key\" is inactive.");

            return self::FAILURE;
        }
        // Rule: no download until a human has read and confirmed the licence (docs/sources.md).
        if (! $source->isLicenseConfirmed()) {
            $this->error("Licence of \"$key\" is not confirmed (status: {$source->license_status}). Read docs/sources.md, then set the licence status to \"confirmed\" in Admin → Knowledge sources. Nothing was downloaded.");

            return self::FAILURE;
        }

        /** @var SourceFetcher $fetcher */
        $fetcher = app($fetchers[$key]);

        $targets = array_values(array_unique(array_filter(array_map('trim', $this->option('target') ?: (array) config("docthub.knowledge.sources.$key.targets", [])))));
        if (! $targets) {
            $this->error('Nothing to fetch: give at least one --target (or configure default targets).');

            return self::INVALID;
        }

        $languages = array_values(array_unique($this->option('lang') ?: $fetcher->defaultLanguages()));
        $unsupported = array_diff($languages, config('docthub.supported_locales'));
        if ($unsupported) {
            $this->error('Unsupported language(s): '.implode(', ', $unsupported).'. Allowed: '.implode(', ', config('docthub.supported_locales')));

            return self::INVALID;
        }

        $rows = [];
        $failed = 0;
        try {
            foreach ($fetcher->fetch($targets, $languages) as $item) {
                if ($item instanceof FetchFailure) {
                    $failed += $item->skipped ? 0 : 1;
                    $item->skipped ? $this->warn($item->message) : Log::error('knowledge:fetch failed', ['source' => $key, 'target' => $item->target, 'language' => $item->language, 'error' => $item->message]);
                    $rows[] = [$item->target, $item->language, $item->skipped ? 'skipped' : 'FAILED', '', $item->message];

                    continue;
                }

                /** @var FetchedDocument $item */
                $result = $recorder->record($source, $item);
                $rows[] = [
                    $item->target, $item->language, $result['outcome'], substr($result['document']->content_hash, 0, 12),
                    $result['document']->raw_path.($result['sent_to_review'] ? " ({$result['sent_to_review']} item(s) sent back to review)" : ''),
                ];
            }
        } catch (FetchException $e) {
            if ($rows) {
                $this->table(['Target', 'Lang', 'Result', 'SHA-256', 'File / message'], $rows);
            }
            $this->error($e->getMessage());
            Log::error('knowledge:fetch aborted', ['source' => $key, 'error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->table(['Target', 'Lang', 'Result', 'SHA-256', 'File / message'], $rows);

        if ($failed) {
            $this->error("$failed document(s) could not be fetched.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
