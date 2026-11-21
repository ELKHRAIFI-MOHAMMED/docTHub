<?php

namespace App\Models;

use App\Models\Concerns\HasReviewStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A medical orientation rule.
 * Matches when: (body_part_id is null OR selected) AND (question/operator/expected_value) AND every clause in `conditions`.
 * `conditions` = [{"question": "<code>", "operator": "in", "value": ["a","b"]}, ...]
 */
class OrientationRule extends Model
{
    use HasReviewStatus, HasTranslations, SoftDeletes;

    public const RESULT_TYPES = ['emergency', 'disease', 'specialty', 'advice'];
    public const OPERATORS = ['equals', 'not_equals', 'in', 'not_in', 'gte', 'lte', 'answered', 'contains'];

    protected string $reviewColumn = 'validation_status';

    protected array $translatable = ['message'];

    protected $fillable = [
        'code', 'name', 'description', 'body_part_id', 'question_id', 'operator', 'expected_value', 'conditions',
        'priority', 'result_type', 'disease_id', 'specialty_id', 'guideline_id', 'emergency_severity', 'weight',
        'message', 'source_name', 'source_url', 'source_document_id', 'validation_status', 'version', 'is_sample',
    ];

    protected $casts = [
        'expected_value' => 'array',
        'conditions' => 'array',
        'is_sample' => 'boolean',
        'priority' => 'integer',
        'weight' => 'integer',
        'version' => 'integer',
    ];

    public function bodyPart(): BelongsTo
    {
        return $this->belongsTo(BodyPart::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SymptomQuestion::class, 'question_id');
    }

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

    public function guideline(): BelongsTo
    {
        return $this->belongsTo(MedicalGuideline::class, 'guideline_id');
    }
}
