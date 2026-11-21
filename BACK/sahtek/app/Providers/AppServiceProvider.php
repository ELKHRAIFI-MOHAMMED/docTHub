<?php

namespace App\Providers;

use App\Models;
use App\Services\Ai\MedicalAssistant;
use App\Services\Ai\NullMedicalAssistant;
use App\Services\Orientation\ContentGate;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentGate::class, fn () => new ContentGate((bool) config('docthub.allow_unreviewed_content')));

        // Future AI provider plugs in here. V2 ships with a no-op implementation.
        $this->app->bind(MedicalAssistant::class, NullMedicalAssistant::class);
    }

    public function boot(): void
    {
        // Stable short names in polymorphic columns (translations, audit_logs, tokens) instead of PHP class names.
        Relation::morphMap([
            'user' => Models\User::class,
            'specialty' => Models\Specialty::class,
            'body_part' => Models\BodyPart::class,
            'symptom' => Models\Symptom::class,
            'disease' => Models\Disease::class,
            'guideline' => Models\MedicalGuideline::class,
            'question' => Models\SymptomQuestion::class,
            'question_option' => Models\QuestionOption::class,
            'rule' => Models\OrientationRule::class,
            'country' => Models\Country::class,
            'city' => Models\City::class,
            'language' => Models\Language::class,
            'doctor' => Models\Doctor::class,
            'patient' => Models\Patient::class,
        ]);
    }
}
