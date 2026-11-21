<?php

namespace App\Http\Requests\Auth;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()\-.]{6,32}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'sex' => ['nullable', Rule::in(Patient::SEXES)],
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('country_id', $this->input('country_id'))],
            'preferred_language' => ['required', Rule::in(config('docthub.supported_locales'))],
            'consents' => ['required', 'array'],
            'consents.terms' => ['accepted'],
            'consents.privacy' => ['accepted'],
            'consents.health_data' => ['accepted'],
        ];
    }
}
