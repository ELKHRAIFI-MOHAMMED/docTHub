<?php

namespace App\Services\Knowledge\Import;

/**
 * Text normalisation for imported knowledge. Removes form only (markup, spacing); never rewrites meaning.
 */
final class Normalizer
{
    /** Valid UTF-8 text without HTML, entities decoded, whitespace collapsed. Paragraph breaks kept with $multiline. */
    public static function text(?string $value, bool $multiline = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new \InvalidArgumentException('Text is not valid UTF-8');
        }

        $value = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $value) ?? $value;
        if ($multiline) {
            // Block boundaries become line breaks so that paragraphs and list items stay readable.
            $value = preg_replace('#<li\b[^>]*>#i', "\n- ", $value) ?? $value;
            $value = preg_replace('#</?(p|div|h[1-6]|ul|ol|br)\b[^>]*>#i', "\n", $value) ?? $value;
        }
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\u{00A0}", ' ', $value);

        if ($multiline) {
            $lines = array_map(fn ($l) => trim(preg_replace('/[ \t\f\v]+/u', ' ', $l) ?? $l), preg_split('/\R/u', $value) ?: []);
            $value = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '');
        } else {
            $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        }

        return $value === '' ? null : $value;
    }

    /** Stable internal code: lowercase snake_case, [a-z0-9_], max 64 chars. */
    public static function code(string $prefix, string $external): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($prefix.'_'.$external)) ?? '', '_');

        return substr($slug, 0, 64);
    }

    /** Comparison key for synonyms: case, accents-insensitive punctuation and spacing ignored. */
    public static function key(string $value): string
    {
        $value = mb_strtolower(self::text($value) ?? '', 'UTF-8');

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value);
    }
}
