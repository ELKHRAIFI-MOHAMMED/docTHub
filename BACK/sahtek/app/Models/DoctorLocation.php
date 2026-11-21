<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorLocation extends Model
{
    protected $fillable = [
        'doctor_id', 'label', 'address', 'country_id', 'city_id', 'postal_code', 'latitude', 'longitude', 'is_primary',
    ];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'is_primary' => 'boolean'];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
