<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CityResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\LanguageResource;
use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Http\Request;

class GeoController extends ApiController
{
    public function languages()
    {
        return LanguageResource::collection(Language::active()->orderBy('id')->get());
    }

    public function countries()
    {
        return CountryResource::collection(Country::active()->withLocaleTranslations()->orderBy('name')->get());
    }

    public function cities(Request $request)
    {
        $request->validate(['country_id' => ['required', 'integer', 'exists:countries,id']]);

        return CityResource::collection(
            City::active()->withLocaleTranslations()->where('country_id', $request->integer('country_id'))->orderBy('name')->get()
        );
    }

    /** Countries where at least one public doctor practises. */
    public function doctorCountries()
    {
        $countries = Country::active()->withLocaleTranslations()
            ->whereIn('id', fn ($q) => $q->select('doctor_locations.country_id')->from('doctor_locations')
                ->join('doctors', 'doctors.id', '=', 'doctor_locations.doctor_id')
                ->where('doctors.status', 'published')->where('doctors.verification_status', 'verified')->whereNull('doctors.deleted_at'))
            ->orderBy('name')->get();

        return CountryResource::collection($countries);
    }

    /** Cities (optionally of one country) where at least one public doctor practises. */
    public function doctorCities(Request $request)
    {
        $request->validate(['country_id' => ['nullable', 'integer', 'exists:countries,id']]);

        $cities = City::active()->withLocaleTranslations()
            ->when($request->filled('country_id'), fn ($q) => $q->where('country_id', $request->integer('country_id')))
            ->whereIn('id', fn ($q) => $q->select('doctor_locations.city_id')->from('doctor_locations')
                ->join('doctors', 'doctors.id', '=', 'doctor_locations.doctor_id')
                ->where('doctors.status', 'published')->where('doctors.verification_status', 'verified')->whereNull('doctors.deleted_at'))
            ->orderBy('name')->get();

        return CityResource::collection($cities);
    }
}
