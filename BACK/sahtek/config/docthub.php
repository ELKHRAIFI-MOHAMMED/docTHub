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
];
