<?php

namespace App\Services\Knowledge;

use App\Models\Disease;
use App\Models\MedicalGuideline;
use App\Models\OrientationRule;

/**
 * When a source document changes, every APPROVED item citing it goes back to "in_review" (never overwritten,
 * never auto-approved). Draft items are left as they are: they are already waiting for a first review.
 */
class LinkedContentReview
{
    /** @param int[] $documentIds */
    public function sendBack(array $documentIds): int
    {
        if (! $documentIds) {
            return 0;
        }

        $n = MedicalGuideline::whereIn('source_document_id', $documentIds)->where('review_status', 'approved')->update(['review_status' => 'in_review']);
        $n += Disease::whereIn('source_document_id', $documentIds)->where('review_status', 'approved')->update(['review_status' => 'in_review']);
        $n += OrientationRule::whereIn('source_document_id', $documentIds)->where('validation_status', 'approved')->update(['validation_status' => 'in_review']);

        return $n;
    }
}
