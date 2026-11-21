<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ground truth reported after the consultation ("what did the doctor say?"). Structured fields only, no free text. */
class AssessmentOutcome extends Model
{
    protected $fillable = [
        'assessment_id', 'consulted', 'consulted_specialty_id', 'diagnosis_disease_id', 'diagnosis_icd11',
        'went_to_emergency', 'helpfulness', 'source', 'collected_at', 'reminder_sent_at',
    ];

    protected $casts = [
        'consulted' => 'boolean',
        'went_to_emergency' => 'boolean',
        'helpfulness' => 'integer',
        'collected_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(SymptomAssessment::class, 'assessment_id');
    }

    public function consultedSpecialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'consulted_specialty_id')->withTrashed();
    }

    public function diagnosisDisease(): BelongsTo
    {
        return $this->belongsTo(Disease::class, 'diagnosis_disease_id')->withTrashed();
    }
}
