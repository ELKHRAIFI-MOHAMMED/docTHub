<?php

namespace App\Http\Requests\Patient;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->patient !== null;
    }

    public function rules(): array
    {
        $countryId = $this->input('country_id', $this->user()?->patient?->country_id);

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-.]{6,32}$/'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today', 'after:1900-01-01'],
            'sex' => ['sometimes', 'nullable', Rule::in(Patient::SEXES)],
            'country_id' => ['sometimes', 'required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'city_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cities', 'id')->where('country_id', $countryId)],
            'preferred_language' => ['sometimes', 'required', Rule::in(config('docthub.supported_locales'))],
            'share_location' => ['sometimes', 'boolean'],
            'email_notifications' => ['sometimes', 'boolean'],
        ];
    }
}
