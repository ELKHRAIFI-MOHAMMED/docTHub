<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iso2' => $this->iso2,
            'name' => $this->translate('name'),
            'phone_code' => $this->phone_code,
            'currency_code' => $this->currency_code,
            'timezone' => $this->timezone,
            'default_language_code' => $this->default_language_code,
            'emergency_number' => $this->emergency_number,
            'emergency_number_verified' => (bool) $this->emergency_number_verified,
        ];
    }
}
