<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Public doctor profile. Never exposes verification documents or internal ids of reviewers. */
class DoctorResource extends JsonResource
{
    /** @var int[]|null favourite doctor ids of the current patient */
    public static ?array $favoriteIds = null;

    public bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $primary = $this->relationLoaded('locations')
            ? $this->locations->sortByDesc('is_primary')->first()
            : null;

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'photo_url' => $this->photo_url,
            'years_of_experience' => $this->years_of_experience,
            'consultation_fee' => $this->consultation_fee !== null ? (float) $this->consultation_fee : null,
            'fee_currency' => $this->fee_currency,
            'is_verified' => $this->isVerified(),
            'verification_status' => $this->verification_status,
            'is_demo' => (bool) $this->is_demo,
            'specialties' => SpecialtyResource::collection($this->whenLoaded('specialties')),
            'languages' => LanguageResource::collection($this->whenLoaded('languages')),
            'city' => $primary?->city ? new CityResource($primary->city) : null,
            'country' => $primary?->country ? new CountryResource($primary->country) : null,
            'distance_km' => isset($this->distance_km) ? round((float) $this->distance_km, 1) : null,
            'is_favorite' => static::$favoriteIds !== null ? in_array($this->id, static::$favoriteIds, true) : null,
        ];

        if ($this->detailed) {
            $data += [
                'bio' => $this->translate('bio'),
                'qualifications' => $this->translate('qualifications'),
                'email' => $this->email,
                'phone' => $this->phone,
                'website' => $this->website,
                'locations' => $this->locations->map(fn ($l) => [
                    'id' => $l->id,
                    'label' => $l->label,
                    'address' => $l->address,
                    'postal_code' => $l->postal_code,
                    'latitude' => $l->latitude,
                    'longitude' => $l->longitude,
                    'is_primary' => (bool) $l->is_primary,
                    'city' => $l->city ? new CityResource($l->city) : null,
                    'country' => $l->country ? new CountryResource($l->country) : null,
                ])->values(),
            ];
        } else {
            $data['bio_excerpt'] = mb_strimwidth((string) $this->translate('bio'), 0, 160, '…');
        }

        return $data;
    }
}
