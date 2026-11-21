<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasReviewStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Disease extends Model
{
    use HasPublicationStatus, HasReviewStatus, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description'];

    protected $fillable = [
        'code', 'name', 'description', 'specialty_id', 'severity', 'status', 'review_status',
        'source_name', 'source_url', 'source_document_id', 'is_sample',
        'external_system', 'external_code', 'external_uri', 'needs_translation', 'version',
    ];

    protected $casts = ['is_sample' => 'boolean', 'needs_translation' => 'array'];

    public function aliases(): MorphMany
    {
        return $this->morphMany(KnowledgeAlias::class, 'aliasable');
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class);
    }

    public function symptoms(): BelongsToMany
    {
        return $this->belongsToMany(Symptom::class, 'disease_symptoms')->withPivot('weight');
    }

    public function bodyParts(): BelongsToMany
    {
        return $this->belongsToMany(BodyPart::class, 'disease_body_parts')->withPivot('weight');
    }

    public function guidelines(): HasMany
    {
        return $this->hasMany(MedicalGuideline::class);
    }
}
