<?php

namespace App\Services\Knowledge\Contracts;

use App\Services\Knowledge\FetchedDocument;
use App\Services\Knowledge\FetchException;
use App\Services\Knowledge\FetchFailure;

/**
 * Downloads raw documents from one external knowledge source, WITHOUT transforming them.
 * Storage, fingerprinting and change detection are done by RawDocumentRecorder, identically for every source.
 */
interface SourceFetcher
{
    /** Stable code, equal to knowledge_sources.code (e.g. "icd11"). */
    public function key(): string;

    /** Languages fetched when none is requested. */
    public function defaultLanguages(): array;

    /**
     * @param  string[]  $targets  what to fetch; meaning depends on the source (ICD-11 codes, search terms…)
     * @param  string[]  $languages  ISO 639-1 codes
     * @return iterable<FetchedDocument|FetchFailure> one item per (target, language); a failure does not stop the others
     *
     * @throws FetchException when the whole source is unusable (missing credentials, authentication refused…)
     */
    public function fetch(array $targets, array $languages): iterable;
}
