<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One document of a knowledge source (a guideline page, a PDF…), with a SHA-256 fingerprint of its content.
 * When the fingerprint changes, every approved item that cites it is sent back to medical review.
 */
class SourceDocument extends Model
{
    public const STATUSES = ['pending', 'active', 'changed', 'unavailable'];

    protected $fillable = [
        'knowledge_source_id', 'url', 'title', 'language', 'published_at', 'retrieved_at', 'last_checked_at',
        'content_hash', 'raw_content', 'status',
    ];

    protected $casts = ['published_at' => 'date', 'retrieved_at' => 'datetime', 'last_checked_at' => 'datetime'];

    protected $hidden = ['raw_content'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function guidelines(): HasMany
    {
        return $this->hasMany(MedicalGuideline::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(OrientationRule::class);
    }

    public function diseases(): HasMany
    {
        return $this->hasMany(Disease::class);
    }
}
