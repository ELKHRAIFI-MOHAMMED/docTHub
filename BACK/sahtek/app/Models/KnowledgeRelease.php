<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An immutable, versioned export of the approved medical knowledge (knowledge:export). */
class KnowledgeRelease extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'version', 'major', 'minor', 'content_hash', 'questionnaire_version', 'ruleset_version', 'counts',
        'file_path', 'file_sha256', 'created_by', 'created_via', 'notes',
    ];

    protected $casts = ['counts' => 'array', 'major' => 'integer', 'minor' => 'integer'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function latestRelease(): ?self
    {
        return static::query()->orderByDesc('major')->orderByDesc('minor')->first();
    }
}
