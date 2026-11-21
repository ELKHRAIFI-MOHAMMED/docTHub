<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A question is only shown when all of its conditions on previous answers are satisfied. */
class QuestionCondition extends Model
{
    protected $fillable = ['question_id', 'depends_on_question_id', 'operator', 'value'];

    protected $casts = ['value' => 'array'];

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(SymptomQuestion::class, 'depends_on_question_id');
    }
}
