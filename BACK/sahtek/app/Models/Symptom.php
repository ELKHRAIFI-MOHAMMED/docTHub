<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Symptom extends Model
{
    use HasPublicationStatus, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description'];

    protected $fillable = [
        'code', 'name', 'description', 'status', 'external_system', 'external_code', 'external_uri',
        'needs_translation', 'source_document_id', 'version',
    ];

    protected $casts = ['needs_translation' => 'array'];

    public function aliases(): MorphMany
    {
        return $this->morphMany(KnowledgeAlias::class, 'aliasable');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class);
    }

    public function diseases(): BelongsToMany
    {
        return $this->belongsToMany(Disease::class, 'disease_symptoms')->withPivot('weight');
    }
}
