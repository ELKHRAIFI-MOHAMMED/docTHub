<?php

namespace App\Services\Ai;

final class AssistantSuggestion
{
    /**
     * @param  string[]  $bodyPartCodes
     * @param  string[]  $symptomCodes
     * @param  array<string, string|string[]>  $answers  question code => option code(s) or value
     */
    public function __construct(
        public readonly array $bodyPartCodes = [],
        public readonly array $symptomCodes = [],
        public readonly array $answers = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return ! $this->bodyPartCodes && ! $this->symptomCodes && ! $this->answers;
    }
}
