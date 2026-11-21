<?php

namespace App\Services\Knowledge\Validation;

final class Issue
{
    public function __construct(
        public readonly string $level,   // error | warning
        public readonly string $check,
        public readonly string $item,
        public readonly string $message,
    ) {
    }

    public function toArray(): array
    {
        return ['level' => $this->level, 'check' => $this->check, 'item' => $this->item, 'message' => $this->message];
    }
}
