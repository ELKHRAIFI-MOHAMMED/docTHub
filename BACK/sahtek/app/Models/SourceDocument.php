<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One version of a source document, identified by (source, url, language) and its SHA-256 fingerprint.
 *
 * Two producers:
 * - knowledge:fetch (API sources): raw response stored untouched on disk (raw_path), one row per fingerprint,
 *   older versions are "superseded";
 * - the page monitor (admin "fetch now", docthub:sources:check): fingerprint of the normalised page text kept in
 *   raw_content, status "changed" when it differs.
 * In both cases approved items citing a changed document are sent back to medical review.
 */
class SourceDocument extends Model
{
    public const STATUSES = ['pending', 'active', 'changed', 'superseded', 'unavailable'];

    protected $fillable = [
        'knowledge_source_id', 'previous_document_id', 'url', 'title', 'language', 'published_at', 'retrieved_at',
        'last_checked_at', 'content_hash', 'raw_content', 'raw_path', 'raw_format', 'raw_bytes', 'http_status',
        'metadata', 'status',
    ];

    protected $casts = [
        'published_at' => 'date', 'retrieved_at' => 'datetime', 'last_checked_at' => 'datetime', 'metadata' => 'array',
    ];

    protected $hidden = ['raw_content'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_document_id');
    }

    /** Latest non-superseded version for an identity. */
    public function scopeCurrentFor(Builder $query, int $sourceId, string $url, string $language): Builder
    {
        return $query->where('knowledge_source_id', $sourceId)->where('url', $url)->where('language', $language)
            ->where('status', '!=', 'superseded')->latest('id');
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
