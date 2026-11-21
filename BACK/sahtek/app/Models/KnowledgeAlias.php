<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A synonym (e.g. MedlinePlus "also called") pointing to one canonical symptom or condition. */
class KnowledgeAlias extends Model
{
    protected $fillable = ['locale', 'alias', 'normalized', 'source_document_id'];

    public function aliasable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class);
    }
}
