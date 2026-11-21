<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A publisher of medical knowledge (WHO, HAS, MedlinePlus…) and the licence under which we may use it. */
class KnowledgeSource extends Model
{
    protected $fillable = ['name', 'publisher', 'base_url', 'license', 'license_url', 'allowed_use', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function documents(): HasMany
    {
        return $this->hasMany(SourceDocument::class);
    }
}
