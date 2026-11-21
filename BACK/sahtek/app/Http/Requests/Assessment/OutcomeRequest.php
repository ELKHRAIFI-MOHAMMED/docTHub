<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Post-consultation outcome. Structured answers only: no free text, so no unintended personal data. */
class OutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consulted' => ['required', 'boolean'],
            'consulted_specialty_id' => ['nullable', 'integer', Rule::exists('specialties', 'id')->whereNull('deleted_at')],
            'diagnosis_disease_id' => ['nullable', 'integer', Rule::exists('diseases', 'id')->whereNull('deleted_at')],
            // ICD-11 stem code, e.g. "CA22" or "BA40.Z".
            'diagnosis_icd11' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Z]{4}(\.[0-9A-Z]{1,4})?$/i'],
            'went_to_emergency' => ['nullable', 'boolean'],
            'helpfulness' => ['nullable', 'integer', 'between:1,5'],
        ];
    }
}
