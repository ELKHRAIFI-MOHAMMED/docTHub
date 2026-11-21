<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasTranslations;

    protected array $translatable = ['name'];

    protected $fillable = [
        'iso2', 'name', 'phone_code', 'currency_code', 'timezone', 'default_language_code',
        'emergency_number', 'emergency_number_verified', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'emergency_number_verified' => 'boolean'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }
}
