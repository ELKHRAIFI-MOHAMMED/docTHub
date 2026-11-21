<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth,
            'sex' => $this->sex,
            'country' => new CountryResource($this->whenLoaded('country')),
            'city' => new CityResource($this->whenLoaded('city')),
            'preferences' => $this->whenLoaded('preference', fn () => [
                'language_code' => $this->preference?->language_code,
                'share_location' => (bool) $this->preference?->share_location,
                'email_notifications' => (bool) $this->preference?->email_notifications,
            ]),
        ];
    }
}
