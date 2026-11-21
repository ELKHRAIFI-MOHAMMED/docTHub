<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    public const SEXES = ['female', 'male', 'other', 'undisclosed'];

    protected $fillable = ['user_id', 'first_name', 'last_name', 'phone', 'date_of_birth', 'sex', 'country_id', 'city_id'];

    /** Date of birth is health-adjacent personal data: encrypted at rest. */
    protected $casts = ['date_of_birth' => 'encrypted'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function preference(): HasOne
    {
        return $this->hasOne(PatientPreference::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(PatientConsent::class);
    }

    public function favoriteDoctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'favorites')->withTimestamps();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(SymptomAssessment::class);
    }

    public function ageYears(): ?int
    {
        if (! $this->date_of_birth) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($this->date_of_birth)->age;
        } catch (\Throwable) {
            return null;
        }
    }
}
