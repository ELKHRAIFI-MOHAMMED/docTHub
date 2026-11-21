<?php

namespace App\Services\Knowledge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;

/**
 * HTTP calls to external knowledge sources: timeouts, retries with exponential backoff (connection errors, 429 and
 * 5xx only), Retry-After honoured, a minimum interval between two calls to the same source (rate limit) and a
 * maximum body size. Uses Illuminate\Support\Sleep so tests can fake waiting.
 */
class ResilientHttp
{
    /** @var array<string, float> last call per throttle key (seconds, monotonic) */
    private array $lastCallAt = [];

    private readonly array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('docthub.knowledge.http');
    }

    public function timeout(): int
    {
        return (int) $this->config['timeout'];
    }

    public function connectTimeout(): int
    {
        return (int) $this->config['connect_timeout'];
    }

    /**
     * @param  callable(): Response  $request  builds and sends ONE request (called again on each attempt)
     * @param  float  $minIntervalSeconds  minimum delay between two calls with the same $throttleKey
     *
     * @throws FetchException after the last failed attempt
     */
    public function send(string $throttleKey, float $minIntervalSeconds, callable $request, string $what): Response
    {
        $attempts = 1 + max(0, (int) $this->config['retries']);

        for ($attempt = 1; ; $attempt++) {
            $this->throttle($throttleKey, $minIntervalSeconds);

            try {
                $response = $request();
            } catch (ConnectionException $e) {
                if ($attempt >= $attempts) {
                    throw new FetchException("$what: connection failed or timed out after $attempt attempt(s) ({$e->getMessage()})", 0, $e);
                }
                Sleep::for($this->backoffMs($attempt))->milliseconds();

                continue;
            }

            if ($response->status() === 429 || $response->serverError()) {
                if ($attempt >= $attempts) {
                    throw new FetchException("$what: HTTP {$response->status()} after $attempt attempt(s)");
                }
                Sleep::for($this->retryAfterMs($response) ?? $this->backoffMs($attempt))->milliseconds();

                continue;
            }

            if (strlen($response->body()) > (int) $this->config['max_bytes']) {
                throw new FetchException("$what: response larger than {$this->config['max_bytes']} bytes, refused");
            }

            return $response;
        }
    }

    /** 1st retry = base, then ×2 each time, capped. */
    public function backoffMs(int $attempt): int
    {
        return (int) min($this->config['max_backoff_ms'], $this->config['backoff_ms'] * (2 ** ($attempt - 1)));
    }

    private function retryAfterMs(Response $response): ?int
    {
        $header = trim((string) $response->header('Retry-After'));
        if ($header === '' || ! ctype_digit($header)) {
            return null;
        }

        return (int) min((int) $header * 1000, (int) $this->config['max_retry_after_ms']);
    }

    private function throttle(string $key, float $minIntervalSeconds): void
    {
        $now = hrtime(true) / 1e9;
        if ($minIntervalSeconds > 0 && isset($this->lastCallAt[$key])) {
            $wait = $minIntervalSeconds - ($now - $this->lastCallAt[$key]);
            if ($wait > 0) {
                Sleep::for((int) ceil($wait * 1000))->milliseconds();
            }
        }
        $this->lastCallAt[$key] = hrtime(true) / 1e9;
    }
}
