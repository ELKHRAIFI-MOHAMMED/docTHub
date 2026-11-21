<?php

namespace App\Http\Requests\Assessment;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body_part_ids' => ['required', 'array', 'min:1', 'max:10'],
            'body_part_ids.*' => ['integer', 'distinct', Rule::exists('body_parts', 'id')->where('status', 'published')->whereNull('deleted_at')],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')->where('is_active', true)],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('country_id', $this->input('country_id'))],
            'sex' => ['nullable', Rule::in(Patient::SEXES)],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:120'],
        ];
    }
}
