<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    /** @var array<int, array{options: string[], value: ?string}> */
    public static array $answers = [];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'body_part_id' => $this->body_part_id,
            'text' => $this->translate('text'),
            'help_text' => $this->translate('help_text'),
            'type' => $this->type,
            'min_value' => $this->min_value,
            'max_value' => $this->max_value,
            'unit' => $this->unit,
            'is_required' => (bool) $this->is_required,
            'options' => $this->options->map(fn ($o) => [
                'code' => $o->code,
                'label' => $o->translate('label'),
            ])->values(),
            'answer' => static::$answers[$this->id] ?? null,
        ];
    }
}
