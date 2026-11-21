<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

/** Shape validation only; per-question validation (type, options, range) is done in AssessmentController. */
class SubmitAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1', 'max:50'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.option_codes' => ['nullable', 'array', 'max:30'],
            'answers.*.option_codes.*' => ['string', 'max:64'],
            'answers.*.value' => ['nullable', 'string', 'max:50'],
        ];
    }
}
