<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specialty extends Model
{
    use HasPublicationStatus, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['code', 'name', 'description', 'icon', 'status', 'sort_order'];

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_specialties')->withPivot('is_primary');
    }

    public function diseases(): HasMany
    {
        return $this->hasMany(Disease::class);
    }
}
