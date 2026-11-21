<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A location context is mandatory (country, city or coordinates):
 * the API never returns the whole doctor directory unfiltered.
 */
class SearchDoctorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id', 'required_without_all:city_id,lat'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'language' => ['nullable', 'string', 'max:10', 'exists:languages,code'],
            'max_fee' => ['nullable', 'numeric', 'min:0'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:'.config('docthub.discovery.max_radius_km')],
            'sort' => ['nullable', Rule::in(['relevance', 'distance', 'experience', 'fee', 'name'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['country_id.required_without_all' => 'Select a country or a city, or share your location.'];
    }
}
