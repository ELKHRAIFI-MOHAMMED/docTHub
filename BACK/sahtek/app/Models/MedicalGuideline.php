<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasReviewStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalGuideline extends Model
{
    use HasPublicationStatus, HasReviewStatus, HasTranslations, SoftDeletes;

    public const TYPES = ['self_care', 'when_to_consult', 'prevention', 'emergency'];

    protected array $translatable = ['title', 'content'];

    protected $fillable = [
        'disease_id', 'specialty_id', 'title', 'content', 'guideline_type', 'source_name', 'source_url', 'source_document_id',
        'review_status', 'reviewed_by_name', 'reviewed_at', 'version', 'is_sample', 'status',
    ];

    protected $casts = ['is_sample' => 'boolean', 'reviewed_at' => 'datetime', 'version' => 'integer'];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class);
    }

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
