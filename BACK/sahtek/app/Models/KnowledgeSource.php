<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A publisher of medical knowledge (WHO, NLM…) and the licence under which we may use it.
 * `license_status` is set by a human after reading the licence: nothing is fetched by knowledge:fetch until it is
 * "confirmed". "unclear" means the licence was read and does not clearly allow our use: the source stays blocked.
 */
class KnowledgeSource extends Model
{
    public const LICENSE_STATUSES = ['unverified', 'confirmed', 'unclear'];

    protected $fillable = [
        'code', 'name', 'publisher', 'base_url', 'license', 'license_url', 'license_status', 'license_checked_at',
        'allowed_use', 'attribution', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean', 'license_checked_at' => 'date'];

    protected $attributes = ['license_status' => 'unverified', 'is_active' => true];

    protected static function booted(): void
    {
        // Date of the human confirmation, when none was given explicitly.
        static::saving(function (self $source) {
            if ($source->isDirty('license_status') && $source->license_status === 'confirmed' && ! $source->license_checked_at) {
                $source->license_checked_at = now();
            }
        });
    }

    public function isLicenseConfirmed(): bool
    {
        return $this->license_status === 'confirmed';
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SourceDocument::class);
    }
}
