<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Doctor\SearchDoctorsRequest;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use App\Services\Discovery\DoctorSearch;
use Illuminate\Http\Request;

class DoctorController extends ApiController
{
    public function __construct(private readonly DoctorSearch $search)
    {
    }

    public function index(SearchDoctorsRequest $request)
    {
        return $this->respond($request->validated());
    }

    public function bySpecialty(SearchDoctorsRequest $request, int $specialty)
    {
        return $this->respond(['specialty_id' => $specialty] + $request->validated());
    }

    public function nearby(Request $request)
    {
        $filters = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:'.config('docthub.discovery.max_radius_km')],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->respond($filters + ['sort' => 'distance']);
    }

    public function show(string $doctor)
    {
        $user = auth('sanctum')->user();
        $query = Doctor::query()
            ->withLocaleTranslations()
            ->with([
                'specialties' => fn ($q) => $q->withLocaleTranslations(),
                'languages',
                'locations.city' => fn ($q) => $q->withLocaleTranslations(),
                'locations.country' => fn ($q) => $q->withLocaleTranslations(),
            ]);

        if (! $user?->isAdmin()) {
            $query->publiclyVisible();
        }

        $model = ctype_digit($doctor) ? $query->findOrFail((int) $doctor) : $query->where('slug', $doctor)->firstOrFail();

        $this->loadFavorites([$model->id]);

        return (new DoctorResource($model))->detailed();
    }

    private function respond(array $filters)
    {
        $page = $this->search->search($filters);
        $this->loadFavorites($page->pluck('id')->all());

        return DoctorResource::collection($page)->additional(['meta' => [
            'distance_basis' => isset($filters['lat'], $filters['lng']) ? 'coordinates' : 'none',
        ]]);
    }

    private function loadFavorites(array $doctorIds): void
    {
        $patient = $this->currentPatient();
        DoctorResource::$favoriteIds = $patient
            ? $patient->favoriteDoctors()->whereIn('doctors.id', $doctorIds)->pluck('doctors.id')->all()
            : null;
    }
}
