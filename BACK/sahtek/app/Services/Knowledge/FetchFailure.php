<?php

namespace App\Services\Knowledge;

/** One (target, language) that could not be fetched. `skipped` = not attempted on purpose (e.g. language not offered). */
final class FetchFailure
{
    public function __construct(
        public readonly string $target,
        public readonly string $language,
        public readonly string $message,
        public readonly bool $skipped = false,
    ) {
    }
}
