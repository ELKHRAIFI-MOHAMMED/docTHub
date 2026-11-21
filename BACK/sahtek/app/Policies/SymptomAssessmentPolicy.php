<?php

namespace App\Policies;

use App\Models\SymptomAssessment;
use App\Models\User;

class SymptomAssessmentPolicy
{
    /**
     * Guest assessments (no patient) are reachable by whoever holds the unguessable UUID.
     * Patient assessments are reachable only by that patient (or an admin).
     */
    public function access(?User $user, SymptomAssessment $assessment): bool
    {
        if ($assessment->patient_id === null) {
            return true;
        }
        if (! $user) {
            return false;
        }

        return $user->patient?->id === $assessment->patient_id || $user->isAdmin();
    }
}
