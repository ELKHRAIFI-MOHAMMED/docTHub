<?php

namespace App\Console\Commands;

use App\Models\SourceDocument;
use App\Services\Knowledge\SourceFetcher;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** Daily: re-fingerprint every document of active knowledge sources; changed ones send cited content back to review. */
class CheckSources extends Command
{
    protected $signature = 'docthub:sources:check {--document= : Only this source document id}';

    protected $description = 'Fetch knowledge source documents and detect changes';

    public function handle(SourceFetcher $fetcher): int
    {
        $documents = SourceDocument::query()
            ->whereHas('source', fn ($q) => $q->where('is_active', true))
            ->when($this->option('document'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $rows = [];
        foreach ($documents as $doc) {
            try {
                $r = $fetcher->fetch($doc);
                $rows[] = [$doc->id, mb_strimwidth($doc->title, 0, 50, '…'), $r['status'], $r['changed'] ? 'yes' : 'no', $r['sent_to_review']];
            } catch (ValidationException $e) {
                $rows[] = [$doc->id, mb_strimwidth($doc->title, 0, 50, '…'), 'blocked url', '-', 0];
            }
        }

        $this->table(['#', 'Document', 'Status', 'Changed', 'Sent to review'], $rows);

        return self::SUCCESS;
    }
}
