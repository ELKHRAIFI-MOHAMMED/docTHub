<?php

namespace App\Services\Knowledge\Fetchers;

use App\Services\Knowledge\Contracts\SourceFetcher;
use App\Services\Knowledge\FetchedDocument;
use App\Services\Knowledge\FetchException;
use App\Services\Knowledge\FetchFailure;
use App\Services\Knowledge\ResilientHttp;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * WHO ICD-11 API, MMS linearization (see docs/sources.md for the verified references).
 *
 * - OAuth2 client_credentials, scope "icdapi_access", client id/secret sent with HTTP Basic authentication to the
 *   token endpoint; the token (≈1 h) is cached and renewed once on 401.
 * - Every call sends "API-Version: v2", "Accept: application/json" and "Accept-Language: <iso 639-1>"
 *   (both headers are required by the API's OpenAPI definition v2.6.0).
 * - Release: ICD_API_RELEASE if set, otherwise the latest release announced by GET /icd/release/11/mms.
 * - Target = an ICD-11 code (resolved with GET …/{release}/mms/codeinfo/{code}) or "entity:<id>".
 * - One raw JSON document per (entity, language) from GET …/{release}/mms/{id}.
 */
class IcdFetcher implements SourceFetcher
{
    private const TOKEN_CACHE_KEY = 'docthub.knowledge.icd11.token';

    private readonly array $config;

    public function __construct(private readonly ResilientHttp $http, ?array $config = null)
    {
        $this->config = $config ?? config('docthub.knowledge.sources.icd11');
    }

    public function key(): string
    {
        return 'icd11';
    }

    public function defaultLanguages(): array
    {
        return ['en', 'fr', 'ar'];
    }

    public function fetch(array $targets, array $languages): iterable
    {
        if (blank($this->config['client_id']) || blank($this->config['client_secret'])) {
            throw new FetchException('ICD_API_CLIENT_ID and ICD_API_CLIENT_SECRET must be set in .env (credentials from https://icd.who.int/icdapi).');
        }

        $this->token(); // fail fast: no point trying every target with refused credentials
        $release = $this->config['release'] ?: $this->latestRelease();

        foreach ($targets as $target) {
            try {
                [$entityPath, $code] = $this->resolve($release, $target);
            } catch (FetchException $e) {
                foreach ($languages as $language) {
                    yield new FetchFailure($target, $language, $e->getMessage());
                }

                continue;
            }

            foreach ($languages as $language) {
                $url = $this->base()."/icd/release/11/$release/mms/$entityPath";
                try {
                    $response = $this->get($url, $language, "ICD-11 $target ($language)");
                } catch (FetchException $e) {
                    yield new FetchFailure($target, $language, $e->getMessage());

                    continue;
                }

                if (! $response->successful()) {
                    yield new FetchFailure($target, $language, "ICD-11 $target ($language): HTTP {$response->status()}");

                    continue;
                }

                $json = $response->json();
                $title = is_array($json) ? ($json['title']['@value'] ?? null) : null;
                if (! is_string($title)) {
                    yield new FetchFailure($target, $language, "ICD-11 $target ($language): unexpected response (no title)");

                    continue;
                }

                yield new FetchedDocument(
                    target: $target,
                    url: $url,
                    language: $language,
                    title: trim(($json['code'] ?? $code ?? '').' '.$title),
                    body: $response->body(),
                    format: 'json',
                    httpStatus: $response->status(),
                    metadata: array_filter([
                        'release' => $release,
                        'linearization' => 'mms',
                        'entity' => $entityPath,
                        'code' => $json['code'] ?? $code,
                        'uri' => $json['@id'] ?? null,
                    ], fn ($v) => $v !== null && $v !== ''),
                );
            }
        }
    }

    /** @return array{0: string, 1: ?string} entity path (id or id/residual) and the code, if the target was a code */
    private function resolve(string $release, string $target): array
    {
        if (preg_match('/^entity:(\d+(?:\/(?:unspecified|other))?)$/', $target, $m)) {
            return [$m[1], null];
        }
        if (! preg_match('/^[0-9A-Z]{4,6}(\.[0-9A-Z]{1,4})?([&\/][0-9A-Z.]{2,12})*$/i', $target)) {
            throw new FetchException("ICD-11 $target: not an ICD-11 code (e.g. 1A00) nor entity:<id>");
        }

        $url = $this->base()."/icd/release/11/$release/mms/codeinfo/".rawurlencode(strtoupper($target));
        $response = $this->get($url, 'en', "ICD-11 codeinfo $target");
        if ($response->status() === 404) {
            throw new FetchException("ICD-11 $target: code not found in release $release");
        }
        if (! $response->successful()) {
            throw new FetchException("ICD-11 codeinfo $target: HTTP {$response->status()}");
        }

        // stemId is the linearization URI, e.g. http://id.who.int/icd/release/11/<release>/mms/<id>[/<residual>]
        $stemId = (string) $response->json('stemId');
        if (! preg_match('#/mms/(\d+(?:/(?:unspecified|other))?)$#', $stemId, $m)) {
            throw new FetchException("ICD-11 codeinfo $target: unexpected stemId");
        }

        return [$m[1], strtoupper($target)];
    }

    private function latestRelease(): string
    {
        $response = $this->get($this->base().'/icd/release/11/mms', 'en', 'ICD-11 release list');
        if (! $response->successful()) {
            throw new FetchException("ICD-11 release list: HTTP {$response->status()}");
        }
        if (! preg_match('#/release/11/([0-9]{4}-[0-9]{2})/mms#', (string) $response->json('latestRelease'), $m)) {
            throw new FetchException('ICD-11 release list: latest release not found; set ICD_API_RELEASE in .env');
        }

        return $m[1];
    }

    private function get(string $url, string $language, string $what): Response
    {
        $send = fn () => $this->http->send('icd11', (float) $this->config['min_interval_seconds'], fn () => Http::withToken($this->token())
            ->withHeaders(['API-Version' => 'v2', 'Accept' => 'application/json', 'Accept-Language' => $language])
            ->timeout($this->http->timeout())->connectTimeout($this->http->connectTimeout())
            ->get($url), $what);

        $response = $send();
        if ($response->status() === 401) { // expired or revoked token: renew once
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $send();
            if ($response->status() === 401) {
                throw new FetchException("$what: access refused (401), check the ICD API credentials");
            }
        }

        return $response;
    }

    private function token(): string
    {
        if ($token = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $token;
        }

        $response = $this->http->send('icd11-token', 0, fn () => Http::asForm()
            ->withBasicAuth($this->config['client_id'], $this->config['client_secret'])
            ->timeout($this->http->timeout())->connectTimeout($this->http->connectTimeout())
            ->post($this->config['token_url'], ['grant_type' => 'client_credentials', 'scope' => 'icdapi_access']), 'ICD-11 token');

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new FetchException("ICD-11 token: authentication refused (HTTP {$response->status()}), check ICD_API_CLIENT_ID / ICD_API_CLIENT_SECRET");
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, (int) $response->json('expires_in', 3600) - 60));

        return $token;
    }

    private function base(): string
    {
        return rtrim($this->config['base_url'], '/');
    }
}
