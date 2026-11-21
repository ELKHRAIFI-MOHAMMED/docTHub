<?php

return [
    /*
    | Use medical content (rules, guidelines, diseases) that is not yet professionally approved.
    | MUST be false in production. Results computed with unreviewed content are flagged.
    */
    'allow_unreviewed_content' => (bool) env('DOCTHUB_ALLOW_UNREVIEWED_CONTENT', false),

    'engine_version' => '1.0.0',

    // Maximum possible conditions returned by the orientation engine.
    'max_conditions' => 5,

    // Disk for public assets (doctor photos) and for private verification documents.
    'public_disk' => env('DOCTHUB_PUBLIC_DISK', 'public'),
    'private_disk' => env('DOCTHUB_PRIVATE_DISK', 'local'),

    // Doctor discovery defaults.
    'discovery' => [
        'default_radius_km' => 25,
        'max_radius_km' => 200,
        'per_page' => 12,
    ],

    // Current version of the legal documents patients consent to at registration.
    'consent_version' => env('DOCTHUB_CONSENT_VERSION', '2025-01'),

    'supported_locales' => ['en', 'fr', 'ar'],

    // Public URL of the web app, used in e-mails (follow-up link).
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'sources' => [
        // Resolve host names before fetching to block private / internal addresses (SSRF). Disable only in tests.
        'resolve_dns' => (bool) env('DOCTHUB_SOURCES_RESOLVE_DNS', true),
    ],

    /*
    | Knowledge pipeline (knowledge:fetch). Raw responses are stored untouched on raw_disk under
    | raw/{source}/{sha256}.{ext}. References for every value below: docs/sources.md.
    */
    'knowledge' => [
        'raw_disk' => env('DOCTHUB_KNOWLEDGE_RAW_DISK', 'local'), // local = storage/app
        'exports_disk' => env('DOCTHUB_KNOWLEDGE_EXPORTS_DISK', 'local'), // exports/knowledge_vX.Y.json

        'fetchers' => [
            'icd11' => App\Services\Knowledge\Fetchers\IcdFetcher::class,
            'medlineplus' => App\Services\Knowledge\Fetchers\MedlinePlusFetcher::class,
        ],

        'http' => [
            'timeout' => (int) env('DOCTHUB_KNOWLEDGE_TIMEOUT', 30),          // seconds
            'connect_timeout' => (int) env('DOCTHUB_KNOWLEDGE_CONNECT_TIMEOUT', 10),
            'retries' => (int) env('DOCTHUB_KNOWLEDGE_RETRIES', 3),            // after the first attempt
            'backoff_ms' => 1000,                                              // 1 s, 2 s, 4 s…
            'max_backoff_ms' => 30_000,
            'max_retry_after_ms' => 60_000,
            'max_bytes' => 50_000_000,
        ],

        'sources' => [
            'icd11' => [
                // Credentials: https://icd.who.int/icdapi → "View API access key". Never commit them.
                'client_id' => env('ICD_API_CLIENT_ID'),
                'client_secret' => env('ICD_API_CLIENT_SECRET'),
                'token_url' => env('ICD_API_TOKEN_URL', 'https://icdaccessmanagement.who.int/connect/token'),
                'base_url' => env('ICD_API_BASE_URL', 'https://id.who.int'),
                'release' => env('ICD_API_RELEASE'), // e.g. 2026-01; empty = latest release announced by the API
                'min_interval_seconds' => (float) env('ICD_API_MIN_INTERVAL', 0.2),
                'targets' => [],
            ],
            'medlineplus' => [
                'base_url' => env('MEDLINEPLUS_WS_URL', 'https://wsearch.nlm.nih.gov/ws/query'),
                'tool' => 'docthub',
                'retmax' => (int) env('MEDLINEPLUS_RETMAX', 10),
                // NLM limit: 85 requests / minute / IP. Keep a margin.
                'requests_per_minute' => (int) env('MEDLINEPLUS_REQUESTS_PER_MINUTE', 80),
                'targets' => [],
            ],
        ],
    ],
];
