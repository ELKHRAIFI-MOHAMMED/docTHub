<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiseaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->translate('name'),
            'description' => $this->translate('description'),
            'severity' => $this->severity,
            'specialty' => new SpecialtyResource($this->whenLoaded('specialty')),
            'symptoms' => SymptomResource::collection($this->whenLoaded('symptoms')),
            'review_status' => $this->review_status,
            'is_sample' => (bool) $this->is_sample,
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
        ];
    }
}
