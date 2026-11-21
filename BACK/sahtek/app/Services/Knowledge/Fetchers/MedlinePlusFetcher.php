<?php

namespace App\Services\Knowledge\Fetchers;

use App\Services\Knowledge\Contracts\SourceFetcher;
use App\Services\Knowledge\FetchedDocument;
use App\Services\Knowledge\FetchException;
use App\Services\Knowledge\FetchFailure;
use App\Services\Knowledge\ResilientHttp;
use Illuminate\Support\Facades\Http;

/**
 * MedlinePlus web service of the U.S. National Library of Medicine (see docs/sources.md).
 *
 * - GET https://wsearch.nlm.nih.gov/ws/query?db=healthTopics&term=<term>&rettype=topic&retmax=<n>&tool=docthub
 *   returns XML (root nlmSearchResult) with the full health topic records.
 * - Health topics exist in English (db=healthTopics) and Spanish only: French and Arabic are reported as skipped,
 *   never translated.
 * - NLM limit: 85 requests per minute per IP; we stay below with a minimum interval between calls (config).
 * - No contact e-mail is sent (optional parameter, it would identify a person).
 */
class MedlinePlusFetcher implements SourceFetcher
{
    /** Languages offered by the web service that the app supports, with their database name. */
    private const DATABASES = ['en' => 'healthTopics'];

    private readonly array $config;

    public function __construct(private readonly ResilientHttp $http, ?array $config = null)
    {
        $this->config = $config ?? config('docthub.knowledge.sources.medlineplus');
    }

    public function key(): string
    {
        return 'medlineplus';
    }

    public function defaultLanguages(): array
    {
        return ['en'];
    }

    public function fetch(array $targets, array $languages): iterable
    {
        foreach ($targets as $term) {
            foreach ($languages as $language) {
                if (! isset(self::DATABASES[$language])) {
                    yield new FetchFailure($term, $language, "MedlinePlus has no health topics in \"$language\" (English and Spanish only)", skipped: true);

                    continue;
                }

                $url = rtrim($this->config['base_url'], '/').'?'.http_build_query([
                    'db' => self::DATABASES[$language],
                    'term' => $term,
                    'rettype' => 'topic',
                    'retmax' => (int) $this->config['retmax'],
                    'tool' => $this->config['tool'],
                ], '', '&', PHP_QUERY_RFC3986);

                try {
                    $response = $this->http->send('medlineplus', 60 / max(1, (int) $this->config['requests_per_minute']), fn () => Http::accept('application/xml')
                        ->timeout($this->http->timeout())->connectTimeout($this->http->connectTimeout())
                        ->get($url), "MedlinePlus \"$term\"");
                } catch (FetchException $e) {
                    yield new FetchFailure($term, $language, $e->getMessage());

                    continue;
                }

                if (! $response->successful()) {
                    yield new FetchFailure($term, $language, "MedlinePlus \"$term\": HTTP {$response->status()}");

                    continue;
                }

                $xml = self::parse($response->body());
                if (! $xml || $xml->getName() !== 'nlmSearchResult') {
                    yield new FetchFailure($term, $language, "MedlinePlus \"$term\": unexpected response (not an nlmSearchResult XML document)");

                    continue;
                }

                yield new FetchedDocument(
                    target: $term,
                    url: $url,
                    language: $language,
                    title: "MedlinePlus health topics: $term",
                    body: $response->body(),
                    format: 'xml',
                    httpStatus: $response->status(),
                    metadata: ['db' => self::DATABASES[$language], 'term' => $term, 'count' => (int) $xml->count],
                );
            }
        }
    }

    /** Parse without network access or entity expansion. */
    private static function parse(string $body): ?\SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);

            return $xml === false ? null : $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
