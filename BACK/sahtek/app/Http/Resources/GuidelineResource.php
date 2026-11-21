<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuidelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->translate('title'),
            'content' => $this->translate('content'),
            'guideline_type' => $this->guideline_type,
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
            'review_status' => $this->review_status,
            'reviewed_at' => $this->reviewed_at?->toDateString(),
            'version' => $this->version,
            'is_sample' => (bool) $this->is_sample,
        ];
    }
}
