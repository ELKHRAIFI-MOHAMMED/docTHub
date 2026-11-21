<?php

namespace App\Services\Discovery;

use App\Models\City;
use App\Models\Doctor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Doctor discovery query.
 *
 * Distance is computed ONLY when the client sends coordinates (explicit geolocation consent in the UI).
 * Without coordinates, results are filtered by the selected city/country and no distance is returned:
 * we never pretend to know an exact distance.
 */
class DoctorSearch
{
    private const EARTH_RADIUS_KM = 6371;

    /**
     * @param  array{q?: string, specialty_id?: int, country_id?: int, city_id?: int, language?: string,
     *               max_fee?: float, lat?: float, lng?: float, radius_km?: float, sort?: string,
     *               per_page?: int, include_unverified?: bool}  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Doctor::query()->select('doctors.*');

        if (empty($filters['include_unverified'])) {
            $query->publiclyVisible();
        }

        $hasCoords = isset($filters['lat'], $filters['lng']);
        $locationFilter = function ($q) use ($filters) {
            if (! empty($filters['city_id'])) {
                $q->where('city_id', $filters['city_id']);
            } elseif (! empty($filters['country_id'])) {
                $q->where('country_id', $filters['country_id']);
            }
        };

        if ($hasCoords) {
            $lat = (float) $filters['lat'];
            $lng = (float) $filters['lng'];
            $radius = min((float) ($filters['radius_km'] ?? config('docthub.discovery.default_radius_km')), (float) config('docthub.discovery.max_radius_km'));

            // Bounding box pre-filter (uses the lat/lng index), then exact Haversine distance.
            $latDelta = $radius / 111.045;
            $lngDelta = $radius / (111.045 * max(cos(deg2rad($lat)), 0.01));
            $distanceSql = '(SELECT MIN('.self::EARTH_RADIUS_KM.' * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(dl.latitude)) * COS(RADIANS(dl.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(dl.latitude)))))'
                .' FROM doctor_locations dl WHERE dl.doctor_id = doctors.id AND dl.latitude IS NOT NULL'
                .' AND dl.latitude BETWEEN ? AND ? AND dl.longitude BETWEEN ? AND ?)';
            $bindings = [$lat, $lng, $lat, $lat - $latDelta, $lat + $latDelta, $lng - $lngDelta, $lng + $lngDelta];

            $query->selectRaw("$distanceSql AS distance_km", $bindings)
                ->whereRaw("$distanceSql <= ?", [...$bindings, $radius]);

            if (! empty($filters['city_id']) || ! empty($filters['country_id'])) {
                $query->whereHas('locations', $locationFilter);
            }
        } else {
            $query->whereHas('locations', $locationFilter);
        }

        if (! empty($filters['specialty_id'])) {
            $query->whereHas('specialties', fn ($q) => $q->where('specialties.id', $filters['specialty_id']));
        }

        if (! empty($filters['language'])) {
            $query->whereHas('languages', fn ($q) => $q->where('code', $filters['language']));
        }

        if (isset($filters['max_fee']) && $filters['max_fee'] !== '') {
            $query->whereNotNull('consultation_fee')->where('consultation_fee', '<=', (float) $filters['max_fee']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.addcslashes(trim($filters['q']), '%_\\').'%';
            $query->where(fn (Builder $q) => $q
                ->where('doctors.first_name', 'like', $term)
                ->orWhere('doctors.last_name', 'like', $term)
                ->orWhereRaw("CONCAT(doctors.first_name, ' ', doctors.last_name) LIKE ?", [$term]));
        }

        $this->applySort($query, $filters['sort'] ?? ($hasCoords ? 'distance' : 'relevance'), $hasCoords, $filters);

        $perPage = max(1, min((int) ($filters['per_page'] ?? config('docthub.discovery.per_page')), 50));

        return $query
            ->with([
                'specialties' => fn ($q) => $q->withLocaleTranslations(),
                'languages',
                'locations.city' => fn ($q) => $q->withLocaleTranslations(),
                'locations.country' => fn ($q) => $q->withLocaleTranslations(),
            ])
            ->paginate($perPage)
            ->withQueryString();
    }

    private function applySort(Builder $query, string $sort, bool $hasCoords, array $filters): void
    {
        switch ($sort) {
            case 'distance':
                if ($hasCoords) {
                    $query->orderBy('distance_km');
                    break;
                }
                // No coordinates: distance sort is meaningless, fall back to relevance.
                // no break
            case 'relevance':
                if (! empty($filters['specialty_id'])) {
                    $query->orderByRaw(
                        '(SELECT MAX(ds.is_primary) FROM doctor_specialties ds WHERE ds.doctor_id = doctors.id AND ds.specialty_id = ?) DESC',
                        [$filters['specialty_id']]
                    );
                }
                $query->orderByDesc('years_of_experience');
                break;
            case 'experience':
                $query->orderByDesc('years_of_experience');
                break;
            case 'fee':
                $query->orderByRaw('consultation_fee IS NULL')->orderBy('consultation_fee');
                break;
            case 'name':
                break;
        }
        $query->orderBy('doctors.last_name')->orderBy('doctors.first_name')->orderBy('doctors.id');
    }

    /** Coordinates of a city, used by the UI as "approximate" origin only when the patient asks for it. */
    public static function cityCoordinates(?int $cityId): ?array
    {
        $city = $cityId ? City::find($cityId) : null;

        return $city?->hasCoordinates() ? ['lat' => $city->latitude, 'lng' => $city->longitude] : null;
    }
}
