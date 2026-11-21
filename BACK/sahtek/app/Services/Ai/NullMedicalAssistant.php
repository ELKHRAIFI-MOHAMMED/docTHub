<?php

namespace App\Services\Ai;

/** Default implementation: AI disabled. */
class NullMedicalAssistant implements MedicalAssistant
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function interpretSymptoms(string $text, array $context = []): AssistantSuggestion
    {
        return new AssistantSuggestion();
    }
}
