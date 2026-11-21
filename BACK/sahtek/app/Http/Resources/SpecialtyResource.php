<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpecialtyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->translate('name'),
            'description' => $this->translate('description'),
            'icon' => $this->icon,
            'is_primary' => $this->whenPivotLoaded('doctor_specialties', fn () => (bool) $this->pivot->is_primary),
        ];
    }
}
