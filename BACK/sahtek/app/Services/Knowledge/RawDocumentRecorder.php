<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeSource;
use App\Models\SourceDocument;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Stores a fetched document untouched and records it in source_documents.
 *
 * - SHA-256 of the raw body; file raw/{source code}/{sha}.{format} on the configured disk (content-addressed: the
 *   same content is written once).
 * - Identity of a document = (source, url, language). Same fingerprint as the current version → "unchanged"
 *   (only last_checked_at moves). New fingerprint for a known identity → "changed": a new row is created, the
 *   previous versions become "superseded", the change is logged ("source modifiée") and approved items citing any
 *   previous version go back to "in_review". Nothing is overwritten or deleted.
 */
class RawDocumentRecorder
{
    public function __construct(private readonly AuditLogger $audit, private readonly LinkedContentReview $review)
    {
    }

    /** @return array{outcome: 'new'|'unchanged'|'changed', document: SourceDocument, sent_to_review: int} */
    public function record(KnowledgeSource $source, FetchedDocument $fetched): array
    {
        $sha = hash('sha256', $fetched->body);
        $format = preg_match('/^[a-z0-9]{1,10}$/', $fetched->format) ? $fetched->format : 'bin';
        $path = "raw/{$source->code}/$sha.$format";
        $disk = Storage::disk(config('docthub.knowledge.raw_disk'));

        if (! $disk->exists($path) && ! $disk->put($path, $fetched->body)) {
            throw new FetchException("Could not write $path");
        }

        $current = SourceDocument::currentFor($source->id, $fetched->url, $fetched->language)->first();

        if ($current && $current->content_hash === $sha) {
            $current->forceFill(['last_checked_at' => now(), 'status' => 'active'])->save();

            return ['outcome' => 'unchanged', 'document' => $current, 'sent_to_review' => 0];
        }

        return DB::transaction(function () use ($source, $fetched, $sha, $path, $format, $current) {
            $attributes = [
                'title' => mb_substr($fetched->title, 0, 500),
                'retrieved_at' => now(),
                'last_checked_at' => now(),
                'content_hash' => $sha,
                'raw_path' => $path,
                'raw_format' => $format,
                'raw_bytes' => strlen($fetched->body),
                'http_status' => $fetched->httpStatus,
                'metadata' => $fetched->metadata ?: null,
                'status' => 'active',
            ];

            // First download of a document created by hand in the admin (no fingerprint yet): fill that row.
            if ($current && $current->content_hash === null) {
                $current->forceFill($attributes)->save();

                return ['outcome' => 'new', 'document' => $current, 'sent_to_review' => 0];
            }

            // Content identical to an OLDER version (e.g. a change was reverted): reactivate that row (unique url+lang+sha).
            $document = SourceDocument::where('knowledge_source_id', $source->id)->where('url', $fetched->url)
                ->where('language', $fetched->language)->where('content_hash', $sha)->first()
                ?? new SourceDocument(['knowledge_source_id' => $source->id, 'url' => $fetched->url, 'language' => $fetched->language]);
            $document->forceFill($attributes + ['previous_document_id' => $current?->id])->save();

            if (! $current) {
                return ['outcome' => 'new', 'document' => $document, 'sent_to_review' => 0];
            }

            $previousIds = SourceDocument::where('knowledge_source_id', $source->id)->where('url', $fetched->url)
                ->where('language', $fetched->language)->whereKeyNot($document->id)->pluck('id')->all();
            SourceDocument::whereKey($previousIds)->update(['status' => 'superseded']);
            $sent = $this->review->sendBack($previousIds);

            Log::warning('Source modifiée', [
                'source' => $source->code, 'url' => $fetched->url, 'language' => $fetched->language,
                'old_hash' => $current->content_hash, 'new_hash' => $sha, 'sent_to_review' => $sent,
            ]);
            $this->audit->log('knowledge.source_changed', $document,
                ['source_document_id' => $current->id, 'hash' => $current->content_hash],
                ['source_document_id' => $document->id, 'hash' => $sha, 'raw_path' => $path, 'sent_to_review' => $sent]);

            return ['outcome' => 'changed', 'document' => $document, 'sent_to_review' => $sent];
        });
    }
}
