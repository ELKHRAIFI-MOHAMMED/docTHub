<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    use HasTranslations;

    protected array $translatable = ['label'];

    protected $fillable = ['question_id', 'code', 'label', 'score', 'symptom_id', 'sort_order'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(SymptomQuestion::class, 'question_id');
    }

    public function symptom(): BelongsTo
    {
        return $this->belongsTo(Symptom::class);
    }
}
