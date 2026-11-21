<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends Model
{
    protected $fillable = ['assessment_id', 'question_id', 'option_codes', 'value'];

    protected $casts = ['option_codes' => 'array'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(SymptomQuestion::class, 'question_id');
    }
}
