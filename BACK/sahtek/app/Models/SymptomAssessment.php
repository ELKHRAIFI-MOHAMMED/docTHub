<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SymptomAssessment extends Model
{
    protected $fillable = [
        'uuid', 'patient_id', 'country_id', 'city_id', 'locale', 'sex', 'age_years', 'body_part_ids', 'status',
        'engine_version', 'questionnaire_version', 'ruleset_version', 'uses_unreviewed_content', 'evaluated_at',
    ];

    protected $casts = [
        'body_part_ids' => 'array',
        'uses_unreviewed_content' => 'boolean',
        'evaluated_at' => 'datetime',
        'age_years' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(fn (SymptomAssessment $a) => $a->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'assessment_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class, 'assessment_id')->orderBy('rank');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AssessmentAlert::class, 'assessment_id');
    }

    public function outcome(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AssessmentOutcome::class, 'assessment_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
