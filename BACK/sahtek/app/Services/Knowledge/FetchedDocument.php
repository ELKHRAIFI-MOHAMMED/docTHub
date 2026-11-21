<?php

namespace App\Services\Knowledge;

/** A raw document exactly as returned by the source (body untouched). */
final class FetchedDocument
{
    public function __construct(
        public readonly string $target,
        public readonly string $url,
        public readonly string $language,
        public readonly string $title,
        public readonly string $body,
        public readonly string $format,       // file extension: json, xml, html
        public readonly int $httpStatus = 200,
        public readonly array $metadata = [],
    ) {
    }
}
