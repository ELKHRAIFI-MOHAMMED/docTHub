<?php

namespace App\Services\Ai;

/**
 * Abstraction for a future natural-language symptom interpreter.
 *
 * Contract:
 *  - It may only SUGGEST catalog codes (body parts, symptoms) and questionnaire answers.
 *  - It never produces a diagnosis, a treatment or a medication.
 *  - Its output is passed through AiSafetyGuard, then the deterministic OrientationEngine
 *    (emergency rules first) produces the actual result.
 *  - Identifiable patient data must be removed (AiSafetyGuard::redactIdentifiers) and explicit
 *    consent recorded before any text is sent to an external provider.
 */
interface MedicalAssistant
{
    public function isEnabled(): bool;

    /** @param array{locale?: string, sex?: string, age_years?: int} $context  Non-identifying context only. */
    public function interpretSymptoms(string $text, array $context = []): AssistantSuggestion;
}
