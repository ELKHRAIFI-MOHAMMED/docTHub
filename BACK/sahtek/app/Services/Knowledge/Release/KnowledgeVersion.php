<?php

namespace App\Services\Knowledge\Release;

use App\Models\KnowledgeRelease;
use App\Services\Orientation\ContentGate;

/**
 * Knowledge version stamped on assessments:
 * - "X.Y"        the approved content in use is exactly the content of release X.Y;
 * - "unreleased" approved content changed since the last export (run knowledge:export);
 * - "unreviewed" unreviewed content is allowed (development): no release describes what the engine uses.
 */
class KnowledgeVersion
{
    public const UNRELEASED = 'unreleased';

    public const UNREVIEWED = 'unreviewed';

    public function __construct(private readonly ContentGate $gate, private readonly KnowledgeSnapshot $snapshot)
    {
    }

    public function current(): string
    {
        if ($this->gate->allowsUnreviewed()) {
            return self::UNREVIEWED;
        }

        $release = KnowledgeRelease::where('content_hash', $this->snapshot->currentHash())
            ->orderByDesc('major')->orderByDesc('minor')->first();

        return $release?->version ?? self::UNRELEASED;
    }
}
