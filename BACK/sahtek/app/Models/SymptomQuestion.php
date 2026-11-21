<?php

namespace App\Models;

use App\Models\Concerns\HasPublicationStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SymptomQuestion extends Model
{
    use HasPublicationStatus, HasTranslations, SoftDeletes;

    public const TYPES = ['single_choice', 'multi_choice', 'scale', 'number', 'boolean'];

    protected array $translatable = ['text', 'help_text', 'summary_template'];

    protected $fillable = [
        'code', 'body_part_id', 'text', 'help_text', 'summary_template', 'type', 'min_value', 'max_value', 'unit',
        'is_required', 'sort_order', 'status',
    ];

    protected $casts = ['is_required' => 'boolean', 'min_value' => 'integer', 'max_value' => 'integer'];

    public function bodyPart(): BelongsTo
    {
        return $this->belongsTo(BodyPart::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id')->orderBy('sort_order');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(QuestionCondition::class, 'question_id');
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, ['single_choice', 'multi_choice'], true);
    }
}
