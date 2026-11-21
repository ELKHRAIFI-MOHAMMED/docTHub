<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    use HasTranslations;

    protected array $translatable = ['name'];

    protected $fillable = ['country_id', 'name', 'latitude', 'longitude', 'timezone', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
