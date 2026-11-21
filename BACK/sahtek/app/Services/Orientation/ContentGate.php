<?php

namespace App\Services\Orientation;

/**
 * Decides which medical content review statuses may be used to orient patients.
 *
 * - Production (allow_unreviewed = false): only `approved` content.
 * - Development (allow_unreviewed = true): draft / in_review content too, and results are flagged.
 * - Emergency rules: every non-rejected, non-retired rule is evaluated in all environments,
 *   because missing a warning sign is worse than an unnecessary warning (over-triage).
 */
class ContentGate
{
    public function __construct(private readonly bool $allowUnreviewed)
    {
    }

    public function allowsUnreviewed(): bool
    {
        return $this->allowUnreviewed;
    }

    /** @return string[] */
    public function usableStatuses(): array
    {
        return $this->allowUnreviewed ? ['approved', 'in_review', 'draft'] : ['approved'];
    }

    /** @return string[] */
    public function emergencyStatuses(): array
    {
        return ['approved', 'in_review', 'draft'];
    }
}
