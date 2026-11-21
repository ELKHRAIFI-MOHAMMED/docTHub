<?php

namespace App\Services\Knowledge\Import;

/** One item read from raw documents, normalised, before it is written to symptoms / diseases. */
final class ImportRecord
{
    /**
     * @param  array<string, string>  $names  locale => label, only locales really provided by the source
     * @param  array<string, string>  $descriptions  locale => text
     * @param  array<string, string[]>  $aliases  locale => synonyms
     */
    public function __construct(
        public readonly string $system,
        public readonly string $externalCode,
        public readonly string $code,
        public readonly ?string $uri,
        public readonly int $sourceDocumentId,
        public readonly array $names,
        public readonly array $descriptions = [],
        public readonly array $aliases = [],
    ) {
    }
}
