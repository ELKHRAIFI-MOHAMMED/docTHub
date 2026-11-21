<?php

namespace App\Services\Knowledge;

use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;
use App\Models\SourceDocument;
use App\Services\AuditLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Downloads a source document, fingerprints it (SHA-256 of its normalised text) and, when the fingerprint
 * changes, sends every APPROVED item citing it back to medical review. Nothing is published automatically.
 */
class SourceFetcher
{
    public const MAX_BYTES = 5_000_000;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** @return array{status: string, changed: bool, sent_to_review: int} */
    public function fetch(SourceDocument $document): array
    {
        $url = $document->url;
        $response = null;

        // Redirects are followed manually so that every hop is checked (a public URL must not bounce to a private one).
        for ($hop = 0; $hop <= 3; $hop++) {
            $this->assertSafeUrl($url);
            try {
                $response = Http::timeout(20)
                    ->withHeaders(['User-Agent' => 'docTHub-source-monitor/1.0', 'Accept' => 'text/html,application/xhtml+xml,text/plain,*/*'])
                    ->withOptions(['allow_redirects' => false])
                    ->get($url);
            } catch (ConnectionException) {
                return $this->markUnavailable($document);
            }
            if (! $response->redirect() || ! $response->header('Location')) {
                break;
            }
            $url = self::resolveLocation($url, $response->header('Location'));
        }

        if (! $response->successful()) {
            return $this->markUnavailable($document);
        }

        $text = self::normalise(substr($response->body(), 0, self::MAX_BYTES));
        $hash = hash('sha256', $text);
        $changed = $document->content_hash !== null && $document->content_hash !== $hash;

        $sentToReview = DB::transaction(function () use ($document, $text, $hash, $changed) {
            $document->forceFill([
                'content_hash' => $hash,
                'raw_content' => $text,
                'retrieved_at' => ($changed || ! $document->retrieved_at) ? now() : $document->retrieved_at,
                'last_checked_at' => now(),
                'status' => $changed ? 'changed' : 'active',
            ])->save();

            return $changed ? $this->sendLinkedContentToReview($document) : 0;
        });

        $this->audit->log($changed ? 'knowledge.source_changed' : 'knowledge.source_checked', null, null, [
            'source_document_id' => $document->id, 'hash' => $hash, 'sent_to_review' => $sentToReview,
        ]);

        return ['status' => $document->status, 'changed' => $changed, 'sent_to_review' => $sentToReview];
    }

    private static function resolveLocation(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $p = parse_url($base);
        $origin = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');

        return str_starts_with($location, '/') ? $origin.$location : $origin.'/'.$location;
    }

    /** Text only, whitespace collapsed: markup or spacing changes alone should not trigger a review. */
    public static function normalise(string $body): string
    {
        $body = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $body) ?? $body;
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function sendLinkedContentToReview(SourceDocument $document): int
    {
        $n = MedicalGuideline::where('source_document_id', $document->id)->where('review_status', 'approved')->update(['review_status' => 'in_review']);
        $n += Disease::where('source_document_id', $document->id)->where('review_status', 'approved')->update(['review_status' => 'in_review']);
        $n += OrientationRule::where('source_document_id', $document->id)->where('validation_status', 'approved')->update(['validation_status' => 'in_review']);

        return $n;
    }

    private function markUnavailable(SourceDocument $document): array
    {
        $document->forceFill(['status' => 'unavailable', 'last_checked_at' => now()])->save();

        return ['status' => 'unavailable', 'changed' => false, 'sent_to_review' => 0];
    }

    /** SSRF protection: only public http(s) hosts. */
    public function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';
        $fail = fn () => throw ValidationException::withMessages(['url' => ['Only public http(s) addresses can be fetched.']]);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'])) {
            $fail();
        }

        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (config('docthub.sources.resolve_dns', true) ? (gethostbynamel($host) ?: []) : []);
        if (strtolower($host) === 'localhost' || (config('docthub.sources.resolve_dns', true) && ! $ips)) {
            $fail();
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail();
            }
        }
    }
}
