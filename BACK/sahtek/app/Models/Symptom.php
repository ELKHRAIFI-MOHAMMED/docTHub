<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Symptom extends Model
{
    use HasPublicationStatus, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['code', 'name', 'description', 'status'];

    public function diseases(): BelongsToMany
    {
        return $this->belongsToMany(Disease::class, 'disease_symptoms')->withPivot('weight');
    }
}
