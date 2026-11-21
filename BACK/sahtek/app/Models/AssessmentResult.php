<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResult extends Model
{
    protected $fillable = [
        'assessment_id', 'result_type', 'disease_id', 'specialty_id', 'guideline_id', 'score', 'rank', 'matched_rule_ids',
    ];

    protected $casts = ['matched_rule_ids' => 'array', 'score' => 'float', 'rank' => 'integer'];

    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class)->withTrashed();
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class)->withTrashed();
    }

    public function guideline(): BelongsTo
    {
        return $this->belongsTo(MedicalGuideline::class, 'guideline_id')->withTrashed();
    }
}
